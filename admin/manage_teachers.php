<?php
include '../includes/header.php';
requireLogin();

// Check if user is admin or teacher
$userRole = getUserRole();
if ($userRole !== 'admin' && $userRole !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $firstname = cleanInput($_POST['firstname']);
                $lastname = cleanInput($_POST['lastname']);
                $middlename = cleanInput($_POST['middlename']);
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                $sections = isset($_POST['sections']) ? $_POST['sections'] : [];
                
                if ($password !== $confirm_password) {
                    $_SESSION['error'] = "Passwords do not match.";
                    break;
                }
                
                try {
                    $conn->begin_transaction();
                    
                    // Insert into teachers table
                    $stmt = $conn->prepare("INSERT INTO teachers (firstname, lastname, middlename) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $firstname, $lastname, $middlename);
                    
                    if ($stmt->execute()) {
                        $teacher_id = $conn->insert_id;
                        
                        // Insert section assignments
                        if (!empty($sections)) {
                            $stmt = $conn->prepare("INSERT INTO teacher_sections (teacher_id, section_id) VALUES (?, ?)");
                            foreach ($sections as $section_id) {
                                $stmt->bind_param("ii", $teacher_id, $section_id);
                                $stmt->execute();
                            }
                        }
                        
                        // Create username (firstname.lastname)
                        $username = strtolower($firstname . '.' . $lastname);
                        
                        // Check if username exists and append number if needed
                        $count = 1;
                        $original_username = $username;
                        while (true) {
                            $check = $conn->prepare("SELECT username FROM users WHERE username = ?");
                            $check->bind_param("s", $username);
                            $check->execute();
                            if ($check->get_result()->num_rows === 0) {
                                break;
                            }
                            $username = $original_username . $count;
                            $count++;
                        }
                        
                        // Hash password and create user account
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("INSERT INTO users (username, password, role, reference_id) VALUES (?, ?, 'teacher', ?)");
                        $stmt->bind_param("sss", $username, $hashed_password, $teacher_id);
                        $stmt->execute();
                        
                        $conn->commit();
                        $_SESSION['success'] = "Teacher added successfully! Username: " . $username;
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error adding teacher: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get all teachers
$query = "SELECT t.*, u.username 
          FROM teachers t 
          LEFT JOIN users u ON t.teacher_id = u.reference_id AND u.role = 'teacher' 
          ORDER BY t.lastname, t.firstname";
$teachers = $conn->query($query);

// Then display alerts from session
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
?>
<!-- Header section with search and title -->
<div class="row mb-4">
    <div class="col-12 d-flex flex-column flex-md-row justify-content-between align-items-stretch gap-3">
        <div class="d-flex align-items-center">
            <h2 class="mb-0 text-primary fw-bold d-flex align-items-center">
                <i class="fas fa-chalkboard-teacher me-2"></i>Teacher Management
            </h2>
        </div>
        <div class="d-flex flex-column flex-md-row gap-3" style="width: 100%; max-width: 600px;">
            <!-- Search Input -->
            <div class="input-group shadow-sm rounded-pill overflow-hidden" style="flex: 1 1 auto; min-width: 200px; height: 45px; border: 1px solid #dee2e6;">
                <span class="input-group-text bg-white border-0 px-3">
                    <i class="fas fa-search text-muted"></i>
                </span>
                <input type="text"
                       id="searchInput"
                       class="form-control border-0 shadow-none"
                       placeholder="Search teachers by name or username..."
                       style="height: 100%;">
                <button class="btn btn-outline-secondary border-0 bg-white"
                        type="button"
                        id="clearSearch"
                        style="padding: 0 1rem;">
                    <i class="fas fa-times text-muted"></i>
                </button>
            </div>
            <?php if ($userRole === 'admin'): ?>
                <!-- Add Teacher Button -->
                <button type="button"
                        class="btn btn-primary rounded-pill shadow-sm px-4 d-flex align-items-center gap-2 flex-shrink-0"
                        data-bs-toggle="modal"
                        data-bs-target="#addTeacherModal"
                        style="height: 45px; font-size: 0.95rem;">
                    <i class="fas fa-plus"></i>
                    <span>Add New Teacher</span>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Teachers Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 700px;">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light sticky-top" style="top: 0; z-index: 1;">
                    <tr class="text-uppercase text-muted" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                        <th class="ps-4" style="width: 50px;">#</th>
                        <th class="ps-3">Name</th>
                        <th>Username</th>
                        <th>Assigned Sections</th>
                        <?php if ($userRole === 'admin'): ?>
                            <th class="text-end pe-4" style="width: 180px;">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php 
                    $counter = 1;
                    while ($teacher = $teachers->fetch_assoc()): 
                        // Get assigned sections
                        $stmt = $conn->prepare("SELECT s.grade_level, s.section_name 
                                               FROM teacher_sections ts 
                                               JOIN sections s ON ts.section_id = s.section_id 
                                               WHERE ts.teacher_id = ?
                                               ORDER BY s.grade_level, s.section_name");
                        $stmt->bind_param("i", $teacher['teacher_id']);
                        $stmt->execute();
                        $sections = $stmt->get_result();
                        
                        $section_list = [];
                        while ($section = $sections->fetch_assoc()) {
                            $section_list[] = "Grade {$section['grade_level']}-{$section['section_name']}";
                        }
                    ?>
                        <tr class="border-bottom">
                            <td class="ps-4 text-muted fw-medium"><?php echo $counter++; ?></td>
                            <td class="ps-3 fw-medium">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px;">
                                        <i class="fas fa-chalkboard-teacher text-primary"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($teacher['lastname'] . ', ' . $teacher['firstname']); ?></div>
                                        <small class="text-muted">ID: <?php echo htmlspecialchars($teacher['teacher_id']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="fas fa-user-circle me-1 text-muted"></i>
                                    <?php echo $teacher['username'] ? htmlspecialchars($teacher['username']) : '<span class="text-muted fst-italic">No account</span>'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($section_list)): ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php if (count($section_list) > 3): ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary">
                                                <?php echo count($section_list) . ' sections'; ?>
                                            </span>
                                        <?php else: ?>
                                            <?php foreach ($section_list as $section): ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary">
                                                    <?php echo htmlspecialchars($section); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">No sections assigned</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($userRole === 'admin'): ?>
                                <td class="text-end pe-4">
                                    <div class="btn-group" role="group">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary edit-teacher" 
                                                data-teacher-id="<?php echo $teacher['teacher_id']; ?>"
                                                data-firstname="<?php echo htmlspecialchars($teacher['firstname']); ?>"
                                                data-lastname="<?php echo htmlspecialchars($teacher['lastname']); ?>"
                                                data-middlename="<?php echo htmlspecialchars($teacher['middlename']); ?>"
                                                data-bs-toggle="tooltip" 
                                                title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger delete-teacher" 
                                                data-id="<?php echo $teacher['teacher_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($teacher['firstname'] . ' ' . $teacher['lastname']); ?>"
                                                data-bs-toggle="tooltip" 
                                                title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($userRole === 'admin'): ?>
    <!-- Add Teacher Modal -->
    <div class="modal fade" id="addTeacherModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light border-0 py-3">
                    <h5 class="modal-title text-primary fw-bold">
                        <i class="fas fa-user-plus me-2"></i>Add New Teacher
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" class="needs-validation" novalidate>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add">
                        <div id="addTeacherAlert" class="alert alert-danger d-none" role="alert"></div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-medium mb-2">First Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-user text-muted"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control ps-2" 
                                           name="firstname" 
                                           required
                                           placeholder="John">
                                </div>
                                <div class="invalid-feedback">
                                    Please provide a first name
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium mb-2">Last Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-user text-muted"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control ps-2" 
                                           name="lastname" 
                                           required
                                           placeholder="Doe">
                                </div>
                                <div class="invalid-feedback">
                                    Please provide a last name
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-medium mb-2">Middle Name</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-user text-muted"></i>
                                </span>
                                <input type="text" 
                                       class="form-control ps-2" 
                                       name="middlename"
                                       placeholder="(Optional)">
                            </div>
                        </div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-medium mb-2">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-lock text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control ps-2" 
                                           name="password" 
                                           required
                                           placeholder="••••••••">
                                </div>
                                <div class="invalid-feedback">
                                    Please provide a password
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium mb-2">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-lock text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control ps-2" 
                                           name="confirm_password" 
                                           required
                                           placeholder="••••••••">
                                </div>
                                <div class="invalid-feedback">
                                    Please confirm your password
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-medium mb-2">Assign Sections <span class="text-danger">*</span></label>
                            <select class="form-select section-select-add" name="sections[]" multiple required>
                                <?php
                                $sections_query = "SELECT section_id, grade_level, section_name 
                                                FROM sections 
                                                ORDER BY grade_level, section_name";
                                $sections = $conn->query($sections_query);
                                $current_grade = null;
                                
                                while ($section = $sections->fetch_assoc()):
                                    if ($current_grade !== $section['grade_level']):
                                        if ($current_grade !== null) echo '</optgroup>';
                                        $current_grade = $section['grade_level'];
                                        echo "<optgroup label='Grade {$section['grade_level']}'>";
                                    endif;
                                ?>
                                    <option value="<?php echo $section['section_id']; ?>">
                                        <?php echo htmlspecialchars($section['section_name']); ?>
                                    </option>
                                <?php 
                                endwhile;
                                if ($current_grade !== null) echo '</optgroup>';
                                ?>
                            </select>
                            <div class="form-text">Hold Ctrl/Cmd to select multiple sections</div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top-0 pt-0 px-4 pb-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-plus me-1"></i> Add Teacher
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Teacher Modal -->
    <div class="modal fade" id="editTeacherModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light border-0 py-3">
                    <h5 class="modal-title text-primary fw-bold">
                        <i class="fas fa-user-edit me-2"></i>Edit Teacher
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editTeacherForm" class="needs-validation" novalidate>
                    <div class="modal-body p-4">
                        <input type="hidden" name="teacher_id" id="edit_teacher_id">
                        <div id="editTeacherAlert" class="alert alert-danger d-none" role="alert"></div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-medium mb-2">First Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-user text-muted"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control ps-2" 
                                           name="firstname" 
                                           id="edit_firstname"
                                           required
                                           placeholder="John">
                                </div>
                                <div class="invalid-feedback">
                                    Please provide a first name
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium mb-2">Last Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-user text-muted"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control ps-2" 
                                           name="lastname" 
                                           id="edit_lastname"
                                           required
                                           placeholder="Doe">
                                </div>
                                <div class="invalid-feedback">
                                    Please provide a last name
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-medium mb-2">Middle Name</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-user text-muted"></i>
                                </span>
                                <input type="text" 
                                       class="form-control ps-2" 
                                       name="middlename"
                                       id="edit_middlename"
                                       placeholder="(Optional)">
                            </div>
                        </div>
                        
                        <div class="alert alert-warning mb-4" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            Leave password fields blank to keep the current password
                        </div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-medium mb-2">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-lock text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control ps-2" 
                                           name="password" 
                                           id="edit_password"
                                           placeholder="••••••••">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium mb-2">Confirm New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-lock text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control ps-2" 
                                           name="confirm_password" 
                                           id="edit_confirm_password"
                                           placeholder="••••••••">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-medium mb-2">Assign Sections <span class="text-danger">*</span></label>
                            <select class="form-select section-select" name="sections[]" multiple required>
                                <?php
                                $sections_query = "SELECT section_id, grade_level, section_name 
                                                FROM sections 
                                                ORDER BY grade_level, section_name";
                                $sections = $conn->query($sections_query);
                                $current_grade = null;
                                
                                if(!$sections) {
                                    echo "<option value=''>No sections available</option>";
                                } else  {
                                    while ($section = $sections->fetch_assoc()):
                                        if ($current_grade !== $section['grade_level']):
                                            if ($current_grade !== null) echo '</optgroup>';
                                            $current_grade = $section['grade_level'];
                                            echo "<optgroup label='Grade {$section['grade_level']}'>";
                                        endif;
                                ?>
                                    <option value="<?php echo $section['section_id']; ?>">
                                        <?php echo htmlspecialchars($section['section_name']); ?>
                                    </option>
                                <?php 
                                    endwhile;
                                    if ($current_grade !== null) echo '</optgroup>';
                                }
                                ?>
                            </select>
                            <div class="form-text">Hold Ctrl/Cmd to select multiple sections</div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top-0 pt-0 px-4 pb-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteTeacherModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-danger text-white border-0 py-3">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p>Are you sure you want to delete <strong><span id="deleteTeacherName"></span></strong>? This action cannot be undone.</p>
                    <p class="text-danger mb-0"><i class="fas fa-exclamation-circle me-2"></i>All associated data will be permanently removed.</p>
                    <input type="hidden" id="deleteTeacherId">
                </div>
                <div class="modal-footer bg-light border-top-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmDeleteTeacher" class="btn btn-danger rounded-pill px-4">
                        <i class="fas fa-trash-alt me-1"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add JavaScript -->
    <script>
    $(document).ready(function() {
        // Function to show alerts
        function showAlert(message, type = 'success') {
            const alert = $('<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">')
                .text(message)
                .append('<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
            
            $('.container-fluid:first').after(alert);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                alert.alert('close');
            }, 5000);
        }

        // Initialize Select2 with proper configuration
        function initSelect2() {
            // For edit teacher modal
            $('.section-select').select2({
                dropdownParent: $('#editTeacherModal'),
                width: '100%',
                placeholder: 'Select sections',
                allowClear: true,
                closeOnSelect: false
            });

            // For add teacher modal
            $('.section-select-add').select2({
                dropdownParent: $('#addTeacherModal'),
                width: '100%',
                placeholder: 'Select sections',
                allowClear: true,
                closeOnSelect: false
            });
        }

        // Initialize on document ready
        initSelect2();

        // Reinitialize when modals are shown
        $('#editTeacherModal, #addTeacherModal').on('shown.bs.modal', function() {
            initSelect2();
        });

        // Form validation for add teacher form
        const addTeacherForm = document.getElementById('addTeacherForm');
        if (addTeacherForm) {
            addTeacherForm.addEventListener('submit', function(event) {
                if (!addTeacherForm.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                addTeacherForm.classList.add('was-validated');
            });
        }

        // Form validation for edit teacher form
        const editTeacherForm = document.getElementById('editTeacherForm');
        if (editTeacherForm) {
            editTeacherForm.addEventListener('submit', function(event) {
                if (!editTeacherForm.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                editTeacherForm.classList.add('was-validated');
            });
        }

        // Edit Teacher
        $('.edit-teacher').on('click', function() {
            const button = $(this);
            const id = button.data('teacher-id');
            const firstname = button.data('firstname');
            const lastname = button.data('lastname');
            const middlename = button.data('middlename');
            
            // Reset form and remove validation classes
            $('#editTeacherForm')[0].reset();
            $('#editTeacherForm').removeClass('was-validated');
            
            // Set form values
            $('#edit_teacher_id').val(id);
            $('#edit_firstname').val(firstname);
            $('#edit_lastname').val(lastname);
            $('#edit_middlename').val(middlename || '');
            
            // Show loading state
            const modal = $('#editTeacherModal');
            const submitBtn = modal.find('button[type="submit"]');
            const originalBtnText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading...');
            
            // Clear previous selections
            $('.section-select').val(null).trigger('change');
            
            // Get assigned sections
            $.ajax({
                url: '<?php echo BASE_URL; ?>/admin/api/get_teacher_sections.php',
                method: 'GET',
                data: { teacher_id: id },
                dataType: 'json',
                success: function(response) {
                    try {
                        const sections = Array.isArray(response) ? response : [];
                        if (sections.length > 0) {
                $('.section-select').val(sections).trigger('change');
            }
                    } catch (e) {
                        console.error('Error parsing sections:', e);
                        showAlert('Error loading teacher sections', 'danger');
                    }
                },
                error: function(xhr) {
                    console.error('AJAX Error:', xhr);
                    showAlert('Failed to load teacher sections. Please try again.', 'danger');
                },
                complete: function() {
                    // Re-enable button and restore text
                    submitBtn.prop('disabled', false).html(originalBtnText);
                    // Show the modal after content is loaded
                    modal.modal('show');
                }
            });
        });
        
        // Handle edit teacher form submission
        $('#editTeacherForm').on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const formData = form.serialize();
            const submitBtn = form.find('button[type="submit"]');
            const originalBtnText = submitBtn.html();
            
            // Show loading state
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving...');
            
            // Validate form
            if (!form[0].checkValidity()) {
                form.addClass('was-validated');
                submitBtn.prop('disabled', false).html(originalBtnText);
                return;
            }
            
            // Check if password fields match if filled
            const password = $('#edit_password').val();
            const confirmPassword = $('#edit_confirm_password').val();
            
            if (password || confirmPassword) {
                if (password !== confirmPassword) {
                    showAlert('Passwords do not match', 'danger');
                    submitBtn.prop('disabled', false).html(originalBtnText);
                    return;
                }
            }
            
            // Submit the form via AJAX
            $.ajax({
                url: '<?php echo BASE_URL; ?>/admin/api/update_teacher.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Teacher updated successfully!');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        const errorMsg = response.error || 'Failed to update teacher';
                        showAlert(errorMsg, 'danger');
                        submitBtn.prop('disabled', false).html(originalBtnText);
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'Failed to update teacher. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMsg = xhr.responseJSON.error;
                    }
                    showAlert(errorMsg, 'danger');
                    submitBtn.prop('disabled', false).html(originalBtnText);
                }
            });
        });
        
        // Delete Teacher
        let deleteButton;
        
        $('.delete-teacher').on('click', function(e) {
            e.preventDefault();
            deleteButton = $(this);
            const teacherName = deleteButton.data('name') || 'this teacher';
            const teacherId = deleteButton.data('id');
            
            // Set the teacher name and ID in the modal
            $('#deleteTeacherName').text(teacherName);
            $('#deleteTeacherId').val(teacherId);
            
            // Reset and show the modal
            $('#deleteTeacherModal').modal('show');
        });
        
        // Handle delete confirmation
        $('#confirmDeleteTeacher').on('click', function() {
            const button = $(this);
            const teacherId = $('#deleteTeacherId').val();
            const rowToDelete = deleteButton.closest('tr');
            
            if (!teacherId) {
                showAlert('Invalid teacher ID', 'danger');
                return;
            }
            
            // Disable button and show loading state
            const originalBtnText = button.html();
            button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Deleting...');
            
            // Send delete request
            $.ajax({
                url: '<?php echo BASE_URL; ?>/admin/api/delete_teacher.php',
                method: 'POST',
                data: { teacher_id: teacherId },
                dataType: 'json',
                success: function(response) {
                    $('#deleteTeacherModal').modal('hide');
                    
                    if (response.success) {
                        showAlert('Teacher deleted successfully!');
                        // Fade out and remove the row
                        rowToDelete.fadeOut(400, function() {
                            $(this).remove();
                            // Update row numbers
                            $('table tbody tr').each(function(index) {
                                $(this).find('td:first').text(index + 1);
                            });
                        });
                    } else {
                        const errorMsg = response.error || 'Failed to delete teacher';
                        showAlert(errorMsg, 'danger');
                        button.prop('disabled', false).html(originalBtnText);
                    }
                },
                error: function(xhr) {
                    $('#deleteTeacherModal').modal('hide');
                    let errorMsg = 'Failed to delete teacher. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMsg = xhr.responseJSON.error;
                    }
                    showAlert(errorMsg, 'danger');
                    button.prop('disabled', false).html(originalBtnText);
                },
                complete: function() {
                    // Re-enable button after a short delay to prevent multiple clicks
                    setTimeout(() => {
                        button.prop('disabled', false).html(originalBtnText);
                    }, 1000);
                }
            });
        });
        
        // Reset delete modal when hidden
        $('#deleteTeacherModal').on('hidden.bs.modal', function() {
            $('#deleteTeacherName').text('');
            $('#deleteTeacherId').val('');
            const button = $('#confirmDeleteTeacher');
            button.prop('disabled', false).html('<i class="fas fa-trash-alt me-1"></i> Delete');
        });
        
        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip({
            trigger: 'hover',
            placement: 'top'
        });
        
        // Handle form submission for add teacher
        $('#addTeacherForm').on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const formData = form.serialize();
            const submitBtn = form.find('button[type="submit"]');
            const originalBtnText = submitBtn.html();
            
            // Show loading state
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Adding...');
            
            // Validate form
            if (!form[0].checkValidity()) {
                form.addClass('was-validated');
                submitBtn.prop('disabled', false).html(originalBtnText);
                return;
            }
            
            // Check if passwords match
            const password = $('input[name="password"]').val();
            const confirmPassword = $('input[name="confirm_password"]').val();
            
            if (password !== confirmPassword) {
                showAlert('Passwords do not match', 'danger');
                submitBtn.prop('disabled', false).html(originalBtnText);
                return;
            }
            
            // Submit the form via AJAX
            $.ajax({
                url: '<?php echo BASE_URL; ?>/admin/api/add_teacher.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showAlert('Teacher added successfully!');
                        form[0].reset();
                        form.removeClass('was-validated');
                        $('.section-select-add').val(null).trigger('change');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        const errorMsg = response.error || 'Failed to add teacher';
                        showAlert(errorMsg, 'danger');
                        submitBtn.prop('disabled', false).html(originalBtnText);
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'Failed to add teacher. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMsg = xhr.responseJSON.error;
                    }
                    showAlert(errorMsg, 'danger');
                    submitBtn.prop('disabled', false).html(originalBtnText);
                }
            });
        });
    });
    </script>

    <!-- Add this JavaScript for search functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const clearSearch = document.getElementById('clearSearch');
        const table = document.querySelector('table');
        const tbody = table.querySelector('tbody');
        const rows = tbody.getElementsByTagName('tr');

        function filterTable(searchTerm) {
            searchTerm = searchTerm.toLowerCase();
            let visibleCount = 0;
            
            Array.from(rows).forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                    // Update the row number
                    row.cells[0].textContent = visibleCount;
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Search input event
        searchInput.addEventListener('input', function() {
            filterTable(this.value);
        });

        // Clear search
        clearSearch.addEventListener('click', function() {
            searchInput.value = '';
            filterTable('');
            searchInput.focus();
        });

        // Add keyboard shortcut (Ctrl + /)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === '/') {
                e.preventDefault();
                searchInput.focus();
            }
        });
    });
    </script>

    <style>
    #searchInput:focus {
        box-shadow: none;
        border-color: #0d6efd;
    }

    .input-group .btn-outline-secondary {
        border-color: #ced4da;
    }

    .input-group .btn-outline-secondary:hover {
        background-color: #e9ecef;
        border-color: #ced4da;
        color: #000;
    }
    </style>
<?php endif; ?>

<!-- Delete Teacher Confirmation Modal -->
<div class="modal fade" id="deleteTeacherModal" tabindex="-1" aria-labelledby="deleteTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteTeacherModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteTeacherName"></strong>?</p>
                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone. This will also delete their user account and all related records.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteTeacher">
                    <i class="fas fa-trash-alt me-1"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<!-- Add this to your <head> section or before </body> -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script> 