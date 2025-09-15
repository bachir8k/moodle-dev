<?php
session_start();

// --- Simple Password Protection ---
$password = 'moodle'; // Simple password, can be changed.
$is_authenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

if (isset($_POST['password'])) {
    if ($_POST['password'] === $password) {
        $_SESSION['authenticated'] = true;
        $is_authenticated = true;
    } else {
        $error = 'Invalid password';
    }
}

if (!$is_authenticated) {
    // --- Login Form ---
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Login</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '</head><body class="bg-light"><div class="container mt-5"><div class="row justify-content-center"><div class="col-md-4">';
    echo '<div class="card"><div class="card-body"><h3>Task Manager Login</h3><form method="POST">';
    echo '<div class="mb-3"><label for="password" class="form-label">Password</label><input type="password" name="password" id="password" class="form-control"></div>';
    if (isset($error)) { echo '<div class="alert alert-danger">'.$error.'</div>'; }
    echo '<button type="submit" class="btn btn-primary">Login</button></form></div></div>';
    echo '</div></div></div></body></html>';
    exit;
}

// --- Main Application Logic ---
$data_file = __DIR__ . '/tasks.json';

function read_data() {
    global $data_file;
    if (!file_exists($data_file)) {
        // Create a default structure if the file doesn't exist
        $default_data = [
            'members' => ['Bachir', 'Khan', 'Ziad', 'Shaheen', 'LCL-AI', 'VPS-AI'],
            'categories' => ['UI', 'Plugin', 'Feature', 'General Fix'],
            'types' => ['Bug Fix', 'New Feature', 'Enhancement'],
            'statuses' => ['To Do', 'In Progress', 'Done', 'Cancelled'],
            'tasks' => []
        ];
        file_put_contents($data_file, json_encode($default_data, JSON_PRETTY_PRINT));
        return $default_data;
    }
    $json_data = file_get_contents($data_file);
    return json_decode($json_data, true);
}

function write_data($data) {
    global $data_file;
    file_put_contents($data_file, json_encode($data, JSON_PRETTY_PRINT));
}

// API Endpoints
if (isset($_REQUEST['action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['action'];
    $data = read_data();

    switch ($action) {
        case 'read':
            echo json_encode($data);
            break;

        case 'create':
            $new_task = [
                'task_id' => empty($data['tasks']) ? 1 : max(array_column($data['tasks'], 'task_id')) + 1,
                'task_category' => (int)$_POST['task_category'],
                'task_name' => $_POST['task_name'],
                'task_description' => $_POST['task_description'],
                'task_priority' => $_POST['task_priority'],
                'task_type' => (int)$_POST['task_type'],
                'task_added_by' => 0, // Hardcoded to Bachir for now
                'task_added_time' => time(),
                'task_expected_date' => $_POST['task_expected_date'],
                'task_status' => 0, // Default to "To Do"
                'task_owner' => (int)$_POST['task_owner'],
                'task_closed_date' => null
            ];
            $data['tasks'][] = $new_task;
            write_data($data);
            echo json_encode(['success' => true, 'task' => $new_task]);
            break;

        case 'update':
            $task_id_to_update = (int)$_POST['task_id'];
            $updated = false;
            foreach ($data['tasks'] as &$task) {
                if ($task['task_id'] === $task_id_to_update) {
                    $task['task_category'] = (int)$_POST['task_category'];
                    $task['task_name'] = $_POST['task_name'];
                    $task['task_description'] = $_POST['task_description'];
                    $task['task_priority'] = $_POST['task_priority'];
                    $task['task_type'] = (int)$_POST['task_type'];
                    $task['task_owner'] = (int)$_POST['task_owner'];
                    $task['task_expected_date'] = $_POST['task_expected_date'];
                    $task['task_status'] = (int)$_POST['task_status'];
                    $updated = true;
                    break;
                }
            }
            if ($updated) {
                write_data($data);
            }
            echo json_encode(['success' => $updated]);
            break;

        case 'delete':
            $task_id_to_delete = (int)$_POST['task_id'];
            $original_count = count($data['tasks']);
            $data['tasks'] = array_filter($data['tasks'], function($task) use ($task_id_to_delete) {
                return $task['task_id'] !== $task_id_to_delete;
            });
            $deleted = count($data['tasks']) < $original_count;
            if ($deleted) {
                 $data['tasks'] = array_values($data['tasks']);
                 write_data($data);
            }
            echo json_encode(['success' => $deleted]);
            break;
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Moodle Dev Tasks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <h1 class="mb-4">Moodle Development Tasks</h1>
        <button class="btn btn-primary mb-3" id="addNewTaskBtn">Add New Task</button>

        <!-- Task table will go here -->
        <div id="task-container">
            <p>Loading tasks...</p>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.js"></script>

    <script>
$(document).ready(function() {
    let currentData = {};

    function loadTasks() {
        $.ajax({
            url: 'tasks.php?action=read',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                currentData = data;
                let members = data.members || [];
                let categories = data.categories || [];
                let types = data.types || [];
                let statuses = data.statuses || [];
                let tasks = data.tasks || [];
                
                let tableHtml = '<table id="tasksTable" class="table table-bordered table-striped" style="width:100%">';
                tableHtml += '<thead class="table-dark"><tr>';
                tableHtml += '<th>ID</th><th>Category</th><th>Name</th><th>Priority</th><th>Expected</th><th>Owner</th><th>Status</th><th class="text-end">Actions</th>';
                tableHtml += '</tr></thead><tbody>';

                tasks.forEach(function(task) {
                    tableHtml += '<tr>';
                    tableHtml += '<td>' + task.task_id + '</td>';
                    tableHtml += '<td>' + (categories[task.task_category] || 'N/A') + '</td>';
                    tableHtml += '<td>' + (task.task_name || '') + '</td>';
                    tableHtml += '<td>' + (task.task_priority || '') + '</td>';
                    tableHtml += '<td>' + (task.task_expected_date || '') + '</td>';
                    tableHtml += '<td>' + (members[task.task_owner] || 'N/A') + '</td>';
                    tableHtml += '<td>' + (statuses[task.task_status] || 'N/A') + '</td>';
                    tableHtml += `<td class="text-end">
                                    <button class="btn btn-sm btn-info view-btn" data-task-id="${task.task_id}"><i class="bi bi-eye-fill"></i></button>
                                    <button class="btn btn-sm btn-primary edit-btn" data-task-id="${task.task_id}"><i class="bi bi-pencil-fill"></i></button>
                                    <button class="btn btn-sm btn-danger delete-btn" data-task-id="${task.task_id}"><i class="bi bi-trash-fill"></i></button>
                                  </td>`;
                    tableHtml += '</tr>';
                });

                tableHtml += '</tbody></table>';
                $('#task-container').html(tableHtml);
                new DataTable('#tasksTable');

                // Populate modal dropdowns
                let catOptions = '';
                categories.forEach((cat, index) => catOptions += `<option value="${index}">${cat}</option>`);
                $('#task_category').html(catOptions);

                let typeOptions = '';
                types.forEach((type, index) => typeOptions += `<option value="${index}">${type}</option>`);
                $('#task_type').html(typeOptions);

                let memberOptions = '';
                members.forEach((member, index) => memberOptions += `<option value="${index}">${member}</option>`);
                $('#task_owner').html(memberOptions);

                let statusOptions = '';
                statuses.forEach((status, index) => statusOptions += `<option value="${index}">${status}</option>`);
                $('#task_status').html(statusOptions);
            },
            error: function() {
                $('#task-container').html('<div class="alert alert-danger">Could not load tasks.</div>');
            }
        });
    }

    // Open modal for new task
    $('#addNewTaskBtn').on('click', function() {
        $('#taskModalLabel').text('Add New Task');
        $('#taskForm')[0].reset();
        $('#task_id').val('');
        $('#taskStatusRow').hide();
        var taskModal = new bootstrap.Modal(document.getElementById('taskModal'));
        taskModal.show();
    });

    // Open modal for viewing task
    $('#task-container').on('click', '.view-btn', function() {
        let taskId = $(this).data('task-id');
        let task = currentData.tasks.find(t => t.task_id === taskId);
        if (task) {
            $('#viewTaskModalBody').html(`
                <dl class="row">
                  <dt class="col-sm-3">ID</dt><dd class="col-sm-9">${task.task_id}</dd>
                  <dt class="col-sm-3">Name</dt><dd class="col-sm-9">${task.task_name}</dd>
                  <dt class="col-sm-3">Description</dt><dd class="col-sm-9">${task.task_description || 'N/A'}</dd>
                  <dt class="col-sm-3">Category</dt><dd class="col-sm-9">${currentData.categories[task.task_category] || 'N/A'}</dd>
                  <dt class="col-sm-3">Priority</dt><dd class="col-sm-9">${task.task_priority}</dd>
                  <dt class="col-sm-3">Type</dt><dd class="col-sm-9">${currentData.types[task.task_type] || 'N/A'}</dd>
                  <dt class="col-sm-3">Status</dt><dd class="col-sm-9">${currentData.statuses[task.task_status] || 'N/A'}</dd>
                  <dt class="col-sm-3">Owner</dt><dd class="col-sm-9">${currentData.members[task.task_owner] || 'N/A'}</dd>
                  <dt class="col-sm-3">Added By</dt><dd class="col-sm-9">${currentData.members[task.task_added_by] || 'N/A'}</dd>
                  <dt class="col-sm-3">Added Time</dt><dd class="col-sm-9">${new Date(task.task_added_time * 1000).toLocaleString()}</dd>
                  <dt class="col-sm-3">Expected Date</dt><dd class="col-sm-9">${task.task_expected_date || 'N/A'}</dd>
                  <dt class="col-sm-3">Closed Date</dt><dd class="col-sm-9">${task.task_closed_date ? new Date(task.task_closed_date * 1000).toLocaleString() : 'N/A'}</dd>
                </dl>
            `);
            var viewModal = new bootstrap.Modal(document.getElementById('viewTaskModal'));
            viewModal.show();
        }
    });

    // Open modal for editing task
    $('#task-container').on('click', '.edit-btn', function() {
        let taskId = $(this).data('task-id');
        let task = currentData.tasks.find(t => t.task_id === taskId);
        if (task) {
            $('#taskModalLabel').text('Edit Task');
            $('#task_id').val(task.task_id);
            $('#task_name').val(task.task_name);
            $('#task_description').val(task.task_description);
            $('#task_category').val(task.task_category);
            $('#task_priority').val(task.task_priority);
            $('#task_type').val(task.task_type);
            $('#task_owner').val(task.task_owner);
            $('#task_status').val(task.task_status);
            $('#task_expected_date').val(task.task_expected_date);
            $('#taskStatusRow').show();
            var taskModal = new bootstrap.Modal(document.getElementById('taskModal'));
            taskModal.show();
        }
    });

    // Save or Update Task
    $('#saveTaskBtn').on('click', function() {
        let taskId = $('#task_id').val();
        let isUpdate = taskId !== '';
        let taskData = {
            action: isUpdate ? 'update' : 'create',
            task_id: taskId,
            task_name: $('#task_name').val(),
            task_description: $('#task_description').val(),
            task_category: $('#task_category').val(),
            task_priority: $('#task_priority').val(),
            task_type: $('#task_type').val(),
            task_owner: $('#task_owner').val(),
            task_expected_date: $('#task_expected_date').val(),
            task_status: $('#task_status').val()
        };

        $.ajax({
            url: 'tasks.php',
            type: 'POST',
            data: taskData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#taskModal').modal('hide');
                    loadTasks(); // Refresh the table
                } else {
                    alert('Error saving task.');
                }
            },
            error: function() {
                alert('Error saving task.');
            }
        });
    });

    // Delete Task
    $('#task-container').on('click', '.delete-btn', function() {
        if (!confirm('Are you sure you want to delete this task?')) {
            return;
        }
        let taskId = $(this).data('task-id');
        $.ajax({
            url: 'tasks.php',
            type: 'POST',
            data: { action: 'delete', task_id: taskId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    loadTasks(); // Refresh the table
                } else {
                    alert('Error deleting task.');
                }
            },
            error: function() {
                alert('Error deleting task.');
            }
        });
    });

    loadTasks();
});
    </script>

<!-- Edit/Add Modal -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="taskModalLabel">Add New Task</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="taskForm">
          <input type="hidden" id="task_id">
          <div class="mb-3">
            <label for="task_name" class="form-label">Task Name</label>
            <input type="text" class="form-control" id="task_name" required>
          </div>
          <div class="mb-3">
            <label for="task_description" class="form-label">Description</label>
            <textarea class="form-control" id="task_description" rows="3"></textarea>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="task_category" class="form-label">Category</label>
              <select class="form-select" id="task_category"></select>
            </div>
            <div class="col-md-6 mb-3">
              <label for="task_priority" class="form-label">Priority</label>
              <select class="form-select" id="task_priority">
                <option value="High">High</option>
                <option value="Medium">Medium</option>
                <option value="Low">Low</option>
                <option value="Critical">Critical</option>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="task_type" class="form-label">Type</label>
              <select class="form-select" id="task_type"></select>
            </div>
            <div class="col-md-6 mb-3">
              <label for="task_owner" class="form-label">Owner</label>
              <select class="form-select" id="task_owner"></select>
            </div>
          </div>
          <div class="mb-3">
            <label for="task_expected_date" class="form-label">Expected Date</label>
            <input type="date" class="form-control" id="task_expected_date">
          </div>
          <div class="row mb-3" id="taskStatusRow" style="display: none;">
            <div class="col-md-6">
                <label for="task_status" class="form-label">Status</label>
                <select class="form-select" id="task_status"></select>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="saveTaskBtn">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewTaskModal" tabindex="-1" aria-labelledby="viewTaskModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewTaskModalLabel">Task Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="viewTaskModalBody">
        <!-- Task details will be injected here by jQuery -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

</body>
</html>