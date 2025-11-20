<?php
require_once __DIR__ . '/../vendor/autoload.php';
include '../includes/header.php';
requireLogin();

if (getUserRole() !== 'admin') {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

// Include Endroid QR Code library
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;

// Create QR code directory if it doesn't exist
$qr_directory = "../qr_codes";
if (!file_exists($qr_directory)) {
    mkdir($qr_directory, 0777, true);
}

// Function to generate QR code
function generateQRCode($data, $file_path) {
    $qr = QrCode::create($data)
        ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh())
        ->setSize(300)
        ->setMargin(10);
    
    $writer = new PngWriter();
    $result = $writer->write($qr);
    $result->saveToFile($file_path);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generate_single':
                $student_id = cleanInput($_POST['student_id']);
                
                // Generate QR code
                $file_name = $qr_directory . "/qr_" . $student_id . ".png";
                generateQRCode($student_id, $file_name);
                
                $_SESSION['success_message'] = "QR code generated for student ID: " . $student_id;
                $_SESSION['generated_file'] = $file_name;
                
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
                
            case 'generate_bulk':
                $section_id = cleanInput($_POST['section_id']);
                
                // Create section directory
                $section_dir = $qr_directory . "/section_" . $section_id;
                if (!file_exists($section_dir)) {
                    mkdir($section_dir, 0777, true);
                }
                
                // Get all students in section
                $stmt = $conn->prepare("SELECT student_id, firstname, lastname FROM students WHERE section_id = ?");
                $stmt->bind_param("i", $section_id);
                $stmt->execute();
                $students = $stmt->get_result();
                
                $generated_count = 0;
                while ($student = $students->fetch_assoc()) {
                    $file_name = $section_dir . "/qr_" . $student['student_id'] . ".png";
                    generateQRCode($student['student_id'], $file_name);
                    $generated_count++;
                }
                
                // Create ZIP archive
                $zip = new ZipArchive();
                $zip_file = $qr_directory . "/section_" . $section_id . "_qrcodes.zip";
                
                if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($section_dir),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    
                    foreach ($files as $file) {
                        if (!$file->isDir()) {
                            $zip->addFile($file->getRealPath(), basename($file->getRealPath()));
                        }
                    }
                    
                    $zip->close();
                    $_SESSION['success_message'] = "Generated " . $generated_count . " QR codes. Download ZIP file below.";
                    $_SESSION['generated_zip'] = $zip_file;
                }
                
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
                
            case 'clean_folder':
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($qr_directory),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                
                foreach ($files as $file) {
                    if ($file->isFile() && ($file->getExtension() === 'png' || $file->getExtension() === 'zip')) {
                        unlink($file->getRealPath());
                    }
                }
                
                $_SESSION['success_message'] = "QR code folder cleaned successfully!";
                
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
        }
    }
}

// At the top of the page, after session start
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['generated_file'])) {
    $generated_file = $_SESSION['generated_file'];
    unset($_SESSION['generated_file']);
}

if (isset($_SESSION['generated_zip'])) {
    $generated_zip = $_SESSION['generated_zip'];
    unset($_SESSION['generated_zip']);
}

// Get all sections for dropdown
$sections = $conn->query("SELECT * FROM sections ORDER BY grade_level, section_name");

?>

<style>
    :root {
        --primary-color: #4e73df;
        --secondary-color: #5a5c69;
        --success-color: #1cc88a;
        --danger-color: #e74a3b;
        --warning-color: #f6c23e;
        --light-bg: #f8f9fc;
        --card-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        --transition: all 0.3s ease-in-out;
    }

    body {
        background-color: var(--light-bg);
    }

    .navbar {
        position: sticky;
        top: 0;
        z-index: 1000;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
    }

    .card {
        border: none;
        border-radius: 0.5rem;
        box-shadow: var(--card-shadow);
        transition: var(--transition);
        margin-bottom: 1.5rem;
        border-left: 0.25rem solid var(--primary-color);
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
    }

    .card-header {
        background-color: white;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        font-weight: 600;
        padding: 1rem 1.25rem;
    }

    .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        padding: 0.5rem 1.5rem;
        font-weight: 500;
        transition: var(--transition);
    }

    .btn-primary:hover {
        background-color: #2e59d9;
        border-color: #2653d4;
        transform: translateY(-1px);
    }

    .form-control, .form-select, .select2-selection {
        border-radius: 0.35rem;
        padding: 0.65rem 1rem;
        border: 1px solid #d1d3e2;
        transition: var(--transition);
    }

    .form-control:focus, .form-select:focus, .select2-selection:focus {
        border-color: #bac8f3;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    }

    .table th {
        background-color: #f8f9fc;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.5px;
    }

    .table td {
        vertical-align: middle;
    }

    .btn-sm {
        padding: 0.25rem 0.75rem;
        font-size: 0.8rem;
    }

    .qr-preview {
        max-width: 300px;
        margin: 0 auto;
        padding: 1rem;
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }

    .spinner-border {
        width: 1.5rem;
        height: 1.5rem;
        border-width: 0.2em;
        display: none;
        margin-left: 0.5rem;
    }

    .btn-loading .spinner-border {
        display: inline-block;
    }

    .feature-icon {
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        background: rgba(78, 115, 223, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        color: var(--primary-color);
        font-size: 1.5rem;
    }

    .file-actions .btn {
        margin: 0 2px;
    }

    @media (max-width: 768px) {
        .card {
            margin-bottom: 1rem;
        }
    }
</style>

<!-- Add Select2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="row mb-5">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">QR Code Generator</h1>
                <p class="mb-0 text-muted">Create and manage QR codes for student attendance</p>
            </div>
            <div>
                <button type="button" class="btn btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#helpModal">
                    <i class="fas fa-question-circle me-2"></i>Help
                </button>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <div>
                <?php echo $success; ?>
                <?php if (isset($generated_file)): ?>
                    <div class="mt-2">
                        <a href="<?php echo str_replace("..", BASE_URL, $generated_file); ?>" 
                           class="btn btn-sm btn-success" download>
                            <i class="fas fa-download me-1"></i> Download QR Code
                        </a>
                    </div>
                <?php endif; ?>
                <?php if (isset($generated_zip)): ?>
                    <div class="mt-2">
                        <a href="<?php echo str_replace("..", BASE_URL, $generated_zip); ?>" 
                           class="btn btn-sm btn-success" download>
                            <i class="fas fa-file-archive me-1"></i> Download ZIP File
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- Single QR Code Generation -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>Single QR Code</h5>
                <span class="badge bg-primary">Quick Generate</span>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="feature-icon mx-auto">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h5 class="mt-3">Single Student QR</h5>
                    <p class="text-muted">Generate a QR code for an individual student</p>
                </div>
                <form method="POST" id="singleQrForm">
                    <input type="hidden" name="action" value="generate_single">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Student ID</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control" name="student_id" 
                                   placeholder="Enter student ID" required>
                        </div>
                        <div class="form-text">Enter the unique student identifier</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100" id="generateSingleBtn">
                        <span class="btn-text">Generate QR Code</span>
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bulk QR Code Generation -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Bulk QR Codes</h5>
                <span class="badge bg-success">Recommended</span>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="feature-icon mx-auto">
                        <i class="fas fa-users"></i>
                    </div>
                    <h5 class="mt-3">Section-wise QR Codes</h5>
                    <p class="text-muted">Generate QR codes for an entire section at once</p>
                </div>
                <form method="POST" id="bulkQrForm">
                    <input type="hidden" name="action" value="generate_bulk">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select Section</label>
                        <select name="section_id" class="form-select select2" required>
                            <option value="">Choose Section...</option>
                            <?php 
                            $sections = $conn->query("SELECT s.*, 
                                (SELECT COUNT(*) FROM students WHERE section_id = s.section_id) as student_count 
                                FROM sections s 
                                ORDER BY grade_level, section_name");
                            
                            while ($section = $sections->fetch_assoc()): 
                                $studentCount = $section['student_count'] ?? 0;
                                $isDisabled = $studentCount === 0 ? 'disabled' : '';
                            ?>
                                <option value="<?php echo $section['section_id']; ?>" <?php echo $isDisabled; ?>>
                                    Grade <?php echo $section['grade_level']; ?> - 
                                    <?php echo $section['section_name']; ?> 
                                    (<?php echo $studentCount; ?> students)
                                    <?php echo $studentCount === 0 ? ' - No students' : ''; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-text">Select a section to generate QR codes for all students</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100" id="generateBulkBtn">
                        <span class="btn-text">Generate QR Codes for Section</span>
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Preview -->
<?php if (isset($generated_file)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-eye me-2"></i>QR Code Preview</h5>
            </div>
            <div class="card-body text-center">
                <div class="qr-preview">
                    <img src="<?php echo str_replace("..", BASE_URL, $generated_file); ?>" 
                         alt="Generated QR Code" class="img-fluid">
                    <div class="mt-3">
                        <p class="text-muted mb-2">Scan this QR code with the attendance app</p>
                        <a href="<?php echo str_replace("..", BASE_URL, $generated_file); ?>" 
                           class="btn btn-sm btn-outline-primary" download>
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Manage QR Files -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><i class="fas fa-folder-open me-2"></i>QR Code Files</h5>
                    <p class="text-muted small mb-0 mt-1">Manage all generated QR code files</p>
                </div>
                <form method="POST" class="d-inline" id="cleanFolderForm">
                    <input type="hidden" name="action" value="clean_folder">
                    <button type="button" class="btn btn-outline-danger btn-sm" 
                            onclick="confirmCleanFolder()">
                        <i class="fas fa-trash-alt me-1"></i> Clean Folder
                    </button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">File Name</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Generated</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $files = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator($qr_directory),
                                RecursiveIteratorIterator::LEAVES_ONLY
                            );
                            
                            foreach ($files as $file) {
                                if ($file->isFile() && $file->getExtension() === 'png' || $file->getExtension() === 'zip') {
                                    $relativePath = str_replace('..', BASE_URL, $file->getPathname());
                                    ?>
                                    <tr>
                                        <td class="align-middle ps-4">
                                            <div class="d-flex align-items-center">
                                                <?php if ($file->getExtension() === 'png'): ?>
                                                    <i class="fas fa-qrcode text-primary me-2"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-file-archive text-warning me-2"></i>
                                                <?php endif; ?>
                                                <span class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars(basename($file->getPathname())); ?>">
                                                    <?php echo htmlspecialchars(basename($file->getPathname())); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="align-middle">
                                            <span class="badge bg-<?php echo $file->getExtension() === 'zip' ? 'warning text-dark' : 'primary'; ?>">
                                                <?php echo strtoupper($file->getExtension()); ?>
                                            </span>
                                        </td>
                                        <td class="align-middle">
                                            <?php 
                                            $size = $file->getSize();
                                            $units = array('B', 'KB', 'MB', 'GB');
                                            $i = 0;
                                            while ($size >= 1024 && $i < count($units) - 1) {
                                                $size /= 1024;
                                                $i++;
                                            }
                                            echo round($size, 2) . ' ' . $units[$i];
                                            ?>
                                        </td>
                                        <td class="align-middle">
                                            <span class="text-muted" title="<?php echo date('F j, Y, g:i a', $file->getMTime()); ?>">
                                                <?php echo date('M j, Y', $file->getMTime()); ?>
                                            </span>
                                        </td>
                                        <td class="align-middle text-end pe-4 file-actions">
                                            <a href="<?php echo $relativePath; ?>" 
                                               class="btn btn-sm btn-outline-primary me-1" 
                                               download
                                               data-bs-toggle="tooltip" 
                                               title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <a href="<?php echo $relativePath; ?>" 
                                               class="btn btn-sm btn-outline-info"
                                               target="_blank"
                                               data-bs-toggle="tooltip" 
                                               title="Preview">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="helpModalLabel"><i class="fas fa-question-circle me-2"></i>QR Code Generator Help</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <h6><i class="fas fa-qrcode text-primary me-2"></i>Single QR Code</h6>
                        <p class="small text-muted">Generate a QR code for an individual student by entering their Student ID.</p>
                        <ol class="small">
                            <li>Enter the student's ID in the input field</li>
                            <li>Click "Generate QR Code"</li>
                            <li>Download or view the generated QR code</li>
                        </ol>
                    </div>
                    <div class="col-md-6 mb-4">
                        <h6><i class="fas fa-layer-group text-primary me-2"></i>Bulk QR Codes</h6>
                        <p class="small text-muted">Generate QR codes for all students in a section at once.</p>
                        <ol class="small">
                            <li>Select a section from the dropdown</li>
                            <li>Click "Generate QR Codes for Section"</li>
                            <li>Download the generated ZIP file containing all QR codes</li>
                        </ol>
                    </div>
                </div>
                <div class="alert alert-info small">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Tip:</strong> QR codes are stored in the <code>qr_codes</code> directory. You can manage them in the table below.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Choose Section...',
        allowClear: true,
        dropdownParent: $('body')
    });

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Handle form submission loading states
    $('#singleQrForm, #bulkQrForm').on('submit', function() {
        const form = $(this);
        const button = form.find('button[type="submit"]');
        
        button.addClass('btn-loading');
        button.prop('disabled', true);
        button.find('.btn-text').text('Generating...');
    });

    // Reset loading state if form validation fails
    $('form').on('invalid-form.validate', function() {
        const button = $(this).find('button[type="submit"]');
        button.removeClass('btn-loading');
        button.prop('disabled', false);
        button.find('.btn-text').text(button.attr('data-original-text') || 'Submit');
    });

    // Store original button text
    $('button[type="submit"]').each(function() {
        $(this).attr('data-original-text', $(this).text().trim());
    });
});

function confirmCleanFolder() {
    Swal.fire({
        title: 'Are you sure?',
        text: "This will permanently delete all generated QR code files. This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete all files',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: 'Cleaning up...',
                text: 'Please wait while we clean up the QR code files.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Submit the form
            $('#cleanFolderForm').submit();
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>