<?php
include '../includes/header.php';
requireLogin();

if (getUserRole() !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle form submission with resubmission prevention
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $section_name = trim(cleanInput($_POST['section_name']));
                $grade_level = cleanInput($_POST['grade_level']);
                // Basic validation
                if ($section_name === '' || !is_numeric($grade_level) || $grade_level < 1 || $grade_level > 12) {
                    $_SESSION['error'] = "Invalid section name or grade level.";
                    break;
                }
                // Check for duplicates
                $dup_stmt = $conn->prepare("SELECT COUNT(*) FROM sections WHERE section_name = ? AND grade_level = ?");
                $dup_stmt->bind_param("si", $section_name, $grade_level);
                $dup_stmt->execute();
                $dup_stmt->bind_result($dup_count);
                $dup_stmt->fetch();
                $dup_stmt->close();
                if ($dup_count > 0) {
                    $_SESSION['error'] = "A section with this name and grade level already exists.";
                    break;
                }
                $stmt = $conn->prepare("INSERT INTO sections (section_name, grade_level) VALUES (?, ?)");
                $stmt->bind_param("si", $section_name, $grade_level);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Section added successfully!";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $_SESSION['error'] = "Error adding section.";
                }
                break;
        }
    }
}

// Display session messages
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
    echo $_SESSION['success'];
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    echo $_SESSION['error'];
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
    unset($_SESSION['error']);
}

// Get all sections
$query = "SELECT * FROM sections ORDER BY grade_level, section_name";
$sections = $conn->query($query);
?>

<div class="row mb-4">
    <div class="col-md-12 d-flex justify-content-between align-items-center">
        <h2 class="mb-0 text-primary fw-bold d-flex align-items-center">
            <i class="fas fa-layer-group me-2"></i>Manage Grade and Sections
        </h2>
        <div class="input-group shadow-sm rounded-pill overflow-hidden me-3" style="width: 320px; height: 45px; border: 1px solid black;">
            <span class="input-group-text bg-white border-end-0 px-3"><i class="fas fa-search text-muted"></i></span>
            <input type="text" id="searchSectionInput" class="form-control ps-0" 
                   placeholder=" Search Grade and Sections..." 
                   style="height: 45px; font-size: 0.95rem;">
        </div>
        <button type="button" class="btn btn-primary rounded-pill shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addSectionModal" style="height: 45px; font-size: 0.95rem;">
            <i class="fas fa-plus me-1"></i> Add Grade and Section
        </button>
    </div>
</div>

<!-- Sections Table -->
<div class="card shadow rounded-4 border-0 bg-light-subtle">
    <div class="card-body pb-0 mb-4">
        <div class="table-responsive rounded-4">
            <table class="table table-hover align-middle table-striped table-borderless mb-0" id="sectionsTable" style="background: #fff;">
                <thead class="table-light sticky-top shadow-sm rounded-4">
                    <tr style="vertical-align: middle;">
                        <th class="text-center">Grade Level</th>
                        <th class="text-center">Section Name</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($section = $sections->fetch_assoc()): ?>
                        <tr><td class="text-center">
                                <span class="status-badge bg-success px-3 py-2 rounded-pill shadow-sm" style="color: #fff;">Grade: <?php echo $section['grade_level']; ?></span>
                            </td>
                            <td class="fw-semibold text-center"><i class="fas fa-door-open me-1 text-secondary"></i>Section: <?php echo $section['section_name']; ?></td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-primary border-0 shadow-sm edit-section" 
                                            data-id="<?php echo $section['section_id']; ?>"
                                            data-name="<?php echo $section['section_name']; ?>"
                                            data-grade="<?php echo $section['grade_level']; ?>"
                                            title="Edit Section">Edit
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger border-0 shadow-sm delete-section" 
                                            data-id="<?php echo $section['section_id']; ?>"
                                            title="Delete Section">Delete
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    // Section search filter
    $(document).ready(function() {
        $('#searchSectionInput').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#sectionsTable tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });
    });
    </script>


    <!-- Add Section Modal -->
    <div class="modal fade" id="addSectionModal" tabindex="-1" aria-labelledby="addSectionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 shadow">
                <div class="modal-header bg-primary text-white rounded-top-4">
                    <h5 class="modal-title fw-bold" id="addSectionModalLabel"><i class="fas fa-plus-circle me-2"></i>Add New Grade and Section</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" autocomplete="off">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Section Name</label>
                            <input type="text" class="form-control rounded-pill" name="section_name" required maxlength="50" placeholder="e.g. A, B, STEM-1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Grade Level</label>
                            <input type="number" class="form-control rounded-pill" name="grade_level" min="1" max="12" required placeholder="e.g. 7">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-save me-1"></i> Add Grade and Section</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Section Modal -->
    <div class="modal fade" id="editSectionModal" tabindex="-1" aria-labelledby="editSectionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 shadow">
                <div class="modal-header bg-warning text-dark rounded-top-4">
                    <h5 class="modal-title fw-bold" id="editSectionModalLabel"><i class="fas fa-edit me-2"></i>Edit Grade and Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editSectionForm" autocomplete="off">
                    <div class="modal-body">
                        <input type="hidden" name="section_id" id="edit_section_id">
                        <div class="mb-3">
                            <label class="form-label">Section Name</label>
                            <input type="text" class="form-control rounded-pill" name="section_name" id="edit_section_name" required maxlength="50">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Grade Level</label>
                            <input type="number" class="form-control rounded-pill" name="grade_level" id="edit_grade_level" required min="1" max="12">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning rounded-pill px-4"><i class="fas fa-save me-1"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Section Modal -->
    <div class="modal fade" id="deleteSectionModal" tabindex="-1" aria-labelledby="deleteSectionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 shadow">
                <div class="modal-header bg-danger text-white rounded-top-4">
                    <h5 class="modal-title fw-bold" id="deleteSectionModalLabel"><i class="fas fa-trash-alt me-2"></i>Delete Grade and Section</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="delete_section_id">
                    <p class="mb-0 fs-5"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Are you sure you want to delete this grade and section?<br><span class="text-danger small">This will also delete all related records.</span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger rounded-pill px-4" id="confirmDeleteSectionBtn"><i class="fas fa-trash-alt me-1"></i> Delete</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Add JavaScript -->
<script>
    $(document).ready(function() {
        function showAlert(message, type = 'success') {
            // Remove any existing alerts first
            $('.alert-toast').alert('close');
            
            const alert = $('<div class="alert alert-' + type + ' alert-dismissible fade show alert-toast" role="alert" style="position: fixed; top: 6%; right: 20px; z-index: 1100; min-width: 300px; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);">')
                .html(`
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">${message}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `);
            
            // Append to body
            $('body').append(alert);
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                alert.alert('close');
            }, 3000);
            
            // Remove from DOM after animation completes
            alert.on('closed.bs.alert', function() {
                $(this).remove();
            });
        }

        // Edit Section
        $('.edit-section').click(function() {
            const button = $(this);
            const id = button.data('id');
            const name = button.data('name');
            const grade = button.data('grade');
            
            $('#edit_section_id').val(id);
            $('#edit_section_name').val(name);
            $('#edit_grade_level').val(grade);
            
            $('#editSectionModal').modal('show');
        });
        
        $('#editSectionForm').submit(function(e) {
            e.preventDefault();
            
            // Get form values
            const sectionName = $('#edit_section_name').val().trim();
            const gradeLevel = $('#edit_grade_level').val().trim();
            const $submitBtn = $(this).find('button[type="submit"]');
            const originalBtnText = $submitBtn.html();
            
            // Client-side validation
            if (sectionName === '') {
                showAlert('Section name is required.', 'danger');
                $('#edit_section_name').focus();
                return;
            }
            
            if (sectionName.length > 50) {
                showAlert('Section name must be 50 characters or less.', 'danger');
                $('#edit_section_name').focus();
                return;
            }
            
            if (!/^\d+$/.test(gradeLevel) || gradeLevel < 1 || gradeLevel > 12) {
                showAlert('Grade level must be a number between 1 and 12.', 'danger');
                $('#edit_grade_level').focus();
                return;
            }
            
            // Disable submit button to prevent double submission
            $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');
            
            const formData = $(this).serialize();
            
            $.ajax({
                url: './api/update_section.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    $('#editSectionModal').modal('hide');
                    if (response.success) {
                        showAlert('Section updated successfully!', 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showAlert(response.error || 'Failed to update section', 'danger');
                        $submitBtn.prop('disabled', false).html(originalBtnText);
                    }
                },
                error: function(xhr) {
                    $('#editSectionModal').modal('hide');
                    const errorMsg = xhr.responseJSON?.error || 'Failed to update section';
                    showAlert(errorMsg, 'danger');
                    $submitBtn.prop('disabled', false).html(originalBtnText);
                }
            });
        });
        
        // Delete Section with modal
        let deleteSectionBtn;
        $('.delete-section').click(function() {
            deleteSectionBtn = $(this);
            const id = deleteSectionBtn.data('id');
            $('#delete_section_id').val(id);
            $('#deleteSectionModal').modal('show');
        });

        $('#confirmDeleteSectionBtn').click(function() {
            const id = $('#delete_section_id').val();
            const button = deleteSectionBtn;
            $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Deleting...');
            $.ajax({
                url: './api/delete_section.php',
                method: 'POST',
                data: { section_id: id },
                dataType: 'json',
                success: function(response) {
                    $('#deleteSectionModal').modal('hide');
                    $('#confirmDeleteSectionBtn').prop('disabled', false).html('<i class="fas fa-trash-alt me-1"></i> Delete');
                    if (response.success) {
                        showAlert('Section deleted successfully!');
                        button.closest('tr').fadeOut(400, function() {
                            $(this).remove();
                        });
                    } else {
                        showAlert(response.error || 'Failed to delete section', 'danger');
                        button.prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    $('#deleteSectionModal').modal('hide');
                    $('#confirmDeleteSectionBtn').prop('disabled', false).html('<i class="fas fa-trash-alt me-1"></i> Delete');
                    const errorMsg = xhr.responseJSON?.error || 'Failed to delete section';
                    showAlert(errorMsg, 'danger');
                    button.prop('disabled', false);
                }
            });
        });
    });
</script>

<?php include '../includes/footer.php'; ?>