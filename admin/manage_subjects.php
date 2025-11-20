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
                $subject_name = trim(cleanInput($_POST['subject_name']));
                $description = trim(cleanInput($_POST['description']));
                
                // Check if subject already exists
                $checkStmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_name = ?");
                $checkStmt->bind_param("s", $subject_name);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    $_SESSION['error'] = "A subject with the name '{$subject_name}' already exists.";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $stmt = $conn->prepare("INSERT INTO subjects (subject_name, description) VALUES (?, ?)");
                    $stmt->bind_param("ss", $subject_name, $description);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Subject added successfully!";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        $_SESSION['error'] = "An error occurred while adding the subject.";
                    }
                }
                break;
        }
    }
}

// Add JavaScript to auto-hide alerts
if (isset($_SESSION['success']) || isset($_SESSION['error'])) {
    echo '<div class="position-fixed top-0 end-0 p-3" style="z-index: 1100">
        <div class="toast-container">';
    
    if (isset($_SESSION['success'])) {
        echo '<div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
            <div class="toast-header bg-success text-white">
                <strong class="me-auto">Success</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">' . $_SESSION['success'] . '</div>
        </div>';
        unset($_SESSION['success']);
    } 
    
    if (isset($_SESSION['error'])) {
        echo '<div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
            <div class="toast-header bg-danger text-white">
                <strong class="me-auto">Error</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">' . $_SESSION['error'] . '</div>
        </div>';
        unset($_SESSION['error']);
    }
    
    echo '</div></div>';
    
    // Initialize toasts
    echo "
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var toastElList = [].slice.call(document.querySelectorAll('.toast'));
        var toastList = toastElList.map(function(toastEl) {
            var toast = new bootstrap.Toast(toastEl, {autohide: true, delay: 5000});
            toast.show();
            return toast;
        });
    });
    </script>
    ";
}

// Get all subjects
$query = "SELECT * FROM subjects ORDER BY subject_name";
$subjects = $conn->query($query);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 fw-bold text-primary mb-0"><i class="fas fa-book me-2"></i>Manage Subjects</h2>
        <nav aria-label="breadcrumb" class="d-none d-md-inline-block">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Subjects</li>
            </ol>
        </nav>
    </div>
    <div>
        <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
            <i class="fas fa-plus-circle me-2"></i>Add New Subject
        </button>
    </div>
</div>

<!-- Search and Filter Card -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Search subjects...">
                    <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-4">
                <select class="form-select" id="sortSelect">
                    <option value="name_asc">Sort by Name (A-Z)</option>
                    <option value="name_desc">Sort by Name (Z-A)</option>
                    <option value="id_asc">Sort by ID (Low to High)</option>
                    <option value="id_desc">Sort by ID (High to Low)</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Subjects Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="subjectsTable">
                <thead class="bg-light">
                    <tr>
                        <th class="py-3" style="width: 80px;">ID</th>
                        <th class="py-3">Subject Name</th>
                        <th class="py-3">Description</th>
                        <th class="py-3 text-end" style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1; // Initialize counter
                    while ($subject = $subjects->fetch_assoc()): 
                    ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td class="fw-semibold"><?php echo $subject['subject_name']; ?></td>
                            <td class="text-muted"><?php echo !empty($subject['description']) ? htmlspecialchars($subject['description']) : '<span class="text-muted fst-italic">No description</span>'; ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-primary edit-subject"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            title="Edit"
                                            data-id="<?php echo $subject['subject_id']; ?>"
                                            data-name="<?php echo $subject['subject_name']; ?>"
                                            data-description="<?php echo htmlspecialchars($subject['description'] ?? ''); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-outline-danger delete-subject"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            title="Delete"
                                            data-id="<?php echo $subject['subject_id']; ?>">
                                        <i class="fas fa-trash-alt"></i> Delete
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

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addSubjectModalLabel">
                    <i class="fas fa-plus-circle me-2"></i>Add New Subject
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="subject_name" class="form-label">Subject Name</label>
                        <input type="text" class="form-control" id="subject_name" name="subject_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1" aria-labelledby="editSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editSubjectModalLabel">
                    <i class="fas fa-edit me-2"></i>Edit Subject
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editSubjectForm">
                <div class="modal-body">
                    <input type="hidden" name="subject_id" id="edit_subject_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Subject Name</label>
                        <input type="text" class="form-control" name="subject_name" id="edit_subject_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteSubjectModal" tabindex="-1" aria-labelledby="deleteSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteSubjectModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the subject: <strong id="deleteSubjectName"></strong>?</p>
                <p class="text-danger mb-0">This action cannot be undone and will permanently delete all related data.</p>
                <input type="hidden" id="deleteSubjectId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add JavaScript -->
<script>
$(document).ready(function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Search functionality
    $('#searchInput').on('keyup', function() {
        var searchText = $(this).val().toLowerCase();
        $('table tbody tr').each(function() {
            var rowText = $(this).text().toLowerCase();
            $(this).toggle(rowText.indexOf(searchText) > -1);
        });
    });

    // Clear search
    $('#clearSearch').on('click', function() {
        $('#searchInput').val('').trigger('keyup');
    });

    // Sort functionality
    $('#sortSelect').on('change', function() {
        var sortValue = $(this).val();
        var $rows = $('table tbody tr').get();
        
        $rows.sort(function(a, b) {
            var aVal, bVal;
            
            if (sortValue.includes('name')) {
                aVal = $(a).find('td:eq(1)').text().toLowerCase();
                bVal = $(b).find('td:eq(1)').text().toLowerCase();
            } else { // id sort
                aVal = parseInt($(a).find('td:first').text(), 10);
                bVal = parseInt($(b).find('td:first').text(), 10);
            }
            
            if (sortValue.includes('_desc')) {
                return aVal < bVal ? 1 : -1;
            } else {
                return aVal > bVal ? 1 : -1;
            }
        });
        
        $.each($rows, function(index, row) {
            $('tbody').append(row);
        });
    });
    function showAlert(message, type = 'success') {
        const alert = $(`
            <div class="alert alert-${type} alert-dismissible fade show shadow-sm border-0" role="alert">
                <div class="d-flex align-items-center">
                    <i class="${type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'} me-2"></i>
                    <span>${message}</span>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        `);
        
        $('.container-fluid').prepend(alert);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            alert.alert('close');
        }, 5000);
    }

    // Edit Subject
    $(document).on('click', '.edit-subject', function() {
        const button = $(this);
        const id = button.data('id');
        const name = button.data('name');
        const description = button.data('description');
        
        $('#edit_subject_id').val(id);
        $('#edit_subject_name').val(name);
        $('#edit_description').val(description);
        
        const editModal = new bootstrap.Modal(document.getElementById('editSubjectModal'));
        editModal.show();
    });
    
    $('#editSubjectForm').submit(function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        const subjectName = $('#edit_subject_name').val().trim();
        const currentId = $('#edit_subject_id').val();
        
        // Check for duplicate subject name
        $.ajax({
            url: '<?php echo BASE_URL; ?>/admin/api/check_subject.php',
            method: 'POST',
            data: { 
                subject_name: subjectName,
                exclude_id: currentId
            },
            dataType: 'json',
            success: function(checkResponse) {
                if (checkResponse.exists) {
                    // Show error message
                    const toast = `
                        <div class="position-fixed top-0 end-0 p-3" style="z-index: 1100">
                            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                                <div class="toast-header bg-danger text-white">
                                    <strong class="me-auto">Error</strong>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                                </div>
                                <div class="toast-body">A subject with the name "${subjectName}" already exists.</div>
                            </div>
                        </div>`;
                    $('body').append(toast);
                    
                    // Auto-hide after 5 seconds
                    setTimeout(() => {
                        $('.toast').toast('hide');
                    }, 5000);
                    
                    // Focus on the subject name field
                    $('#edit_subject_name').focus();
                    return;
                }
                
                // If no duplicate, proceed with the update
                updateSubject(formData);
            },
            error: function() {
                showToast('An error occurred while checking for duplicate subjects.', 'danger');
            }
        });
    });

    // Helper function to show toast messages
    function showToast(message, type = 'success') {
        const toast = `
            <div class="position-fixed top-0 end-0 p-3" style="z-index: 1100">
                <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
                    <div class="toast-header bg-${type} text-white">
                        <strong class="me-auto">${type === 'success' ? 'Success' : 'Error'}</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">${message}</div>
                </div>
            </div>`;
        $('body').append(toast);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            $('.toast').toast('hide');
        }, 5000);
    }
    
    function updateSubject(formData) {
        $.ajax({
            url: '<?php echo BASE_URL; ?>/admin/api/update_subject.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                $('#editSubjectModal').modal('hide');
                if (response.success) {
                    // Show success message
                    const toast = `
                        <div class="position-fixed top-0 end-0 p-3" style="z-index: 1100">
                            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
                                <div class="toast-header bg-success text-white">
                                    <strong class="me-auto">Success</strong>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                                </div>
                                <div class="toast-body">Subject updated successfully!</div>
                            </div>
                        </div>`;
                    $('body').append(toast);
                    
                    // Reload the page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(response.error || 'Failed to update subject', 'danger');
                }
            },
            error: function(xhr) {
                $('#editSubjectModal').modal('hide');
                const errorMsg = xhr.responseJSON?.error || 'Failed to update subject';
                showToast(errorMsg, 'danger');
            }
        });
    }
    
    // Delete Confirmation Modal
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteSubjectModal'));
    let subjectToDelete = null;
    
    // Handle delete button click
    $(document).on('click', '.delete-subject', function() {
        const id = $(this).data('id');
        const name = $(this).closest('tr').find('td:eq(1)').text();
        
        $('#deleteSubjectName').text(name);
        $('#deleteSubjectId').val(id);
        subjectToDelete = $(this).closest('tr');
        
        deleteModal.show();
    });
    
    // Handle delete confirmation
    $('#confirmDeleteBtn').on('click', function() {
        const id = $('#deleteSubjectId').val();
        const $button = $(this);
        const $spinner = $button.find('.spinner-border');
        
        $button.prop('disabled', true);
        $spinner.removeClass('d-none');
        
        $.ajax({
            url: '<?php echo BASE_URL; ?>/admin/api/delete_subject.php',
            method: 'POST',
            data: { subject_id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('Subject deleted successfully!', 'success');
                    subjectToDelete.fadeOut(400, function() {
                        $(this).remove();
                        // Update row numbers
                        $('table tbody tr').each(function(index) {
                            $(this).find('td:first').text(index + 1);
                        });
                    });
                } else {
                    showAlert(response.message || 'Error deleting subject.', 'danger');
                }
            },
            error: function() {
                showAlert('An error occurred while deleting the subject.', 'danger');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.addClass('d-none');
                deleteModal.hide();
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
