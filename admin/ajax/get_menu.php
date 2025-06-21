<?php
session_start();
require_once "../../includes/config.php";
require_once "../../includes/admin_auth.php";

// Check if admin is logged in
requireAdminLogin();

$vendor_id = isset($_GET['vendor_id']) ? $_GET['vendor_id'] : 0;

// Get vendor details
$sql = "SELECT * FROM food_vendors WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$vendor_id]);
$vendor = $stmt->fetch();

if (!$vendor) {
    echo '<div class="text-center text-muted">Vendor not found</div>';
    exit;
}

// Get menu items
$sql = "SELECT * FROM food_menu WHERE vendor_id = ? ORDER BY item_name";
$stmt = $pdo->prepare($sql);
$stmt->execute([$vendor_id]);
$menu_items = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0"><?php echo htmlspecialchars($vendor['vendor_name']); ?>'s Menu</h5>
    <button class="btn btn-primary btn-sm" onclick="showAddItemModal(<?php echo $vendor_id; ?>)">
        <i class="fas fa-plus me-2"></i>Add Item
    </button>
</div>

<?php if(empty($menu_items)): ?>
    <p class="text-muted text-center">No menu items found</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($menu_items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                    <td>₹<?php echo number_format($item['price'], 2); ?></td>
                    <td>
                        <span class="badge <?php echo $item['is_available'] ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo $item['is_available'] ? 'Available' : 'Not Available'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            <button onclick="editMenuItem(<?php echo $item['id']; ?>)" 
                                    class="btn btn-light btn-sm btn-action"
                                    title="Edit Item">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="toggleItemAvailability(<?php echo $item['id']; ?>, <?php echo $item['is_available'] ? 'false' : 'true'; ?>)" 
                                    class="btn btn-light btn-sm btn-action"
                                    title="<?php echo $item['is_available'] ? 'Mark as Unavailable' : 'Mark as Available'; ?>">
                                <i class="fas fa-<?php echo $item['is_available'] ? 'times' : 'check'; ?>"></i>
                            </button>
                            <button onclick="deleteMenuItem(<?php echo $item['id']; ?>)" 
                                    class="btn btn-light btn-sm btn-action text-danger"
                                    title="Delete Item">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Add/Edit Menu Item Modal -->
<div class="modal fade" id="menuItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="menuItemModalTitle">Add Menu Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="menuItemForm">
                    <input type="hidden" id="menu_item_id" name="menu_item_id">
                    <input type="hidden" id="vendor_id" name="vendor_id" value="<?php echo $vendor_id; ?>">
                    <div class="mb-3">
                        <label for="item_name" class="form-label">Item Name</label>
                        <input type="text" class="form-control" id="item_name" name="item_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">Price (₹)</label>
                        <input type="number" class="form-control" id="price" name="price" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_available" name="is_available" checked>
                            <label class="form-check-label" for="is_available">
                                Item is available
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveMenuItem()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show add menu item modal
function showAddItemModal(vendorId) {
    $('#menuItemModalTitle').text('Add Menu Item');
    $('#menuItemForm')[0].reset();
    $('#menu_item_id').val('');
    $('#vendor_id').val(vendorId);
    $('#menuItemModal').modal('show');
}

// Edit menu item
function editMenuItem(itemId) {
    $.ajax({
        url: 'ajax/get_menu_item.php',
        type: 'GET',
        data: { id: itemId },
        success: function(response) {
            const item = JSON.parse(response);
            $('#menuItemModalTitle').text('Edit Menu Item');
            $('#menu_item_id').val(item.id);
            $('#item_name').val(item.item_name);
            $('#description').val(item.description);
            $('#price').val(item.price);
            $('#is_available').prop('checked', item.is_available == 1);
            $('#menuItemModal').modal('show');
        },
        error: function() {
            toastr.error('Failed to load menu item data');
        }
    });
}

// Save menu item
function saveMenuItem() {
    const formData = new FormData($('#menuItemForm')[0]);
    $.ajax({
        url: 'ajax/update_menu_item.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                $('#menuItemModal').modal('hide');
                toastr.success('Menu item saved successfully');
                viewMenu($('#vendor_id').val());
            } else {
                toastr.error(result.message || 'Failed to save menu item');
            }
        },
        error: function() {
            toastr.error('Failed to save menu item');
        }
    });
}

// Toggle item availability
function toggleItemAvailability(itemId, available) {
    $.ajax({
        url: 'ajax/toggle_item_availability.php',
        type: 'POST',
        data: { 
            id: itemId,
            is_available: available
        },
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                toastr.success('Item availability updated');
                viewMenu($('#vendor_id').val());
            } else {
                toastr.error(result.message || 'Failed to update item availability');
            }
        },
        error: function() {
            toastr.error('Failed to update item availability');
        }
    });
}

// Delete menu item
function deleteMenuItem(itemId) {
    Swal.fire({
        title: 'Delete Menu Item',
        text: "This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#95a5a6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/delete_menu_item.php',
                type: 'POST',
                data: { id: itemId },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        toastr.success('Menu item deleted successfully');
                        viewMenu($('#vendor_id').val());
                    } else {
                        toastr.error(result.message || 'Failed to delete menu item');
                    }
                },
                error: function() {
                    toastr.error('Failed to delete menu item');
                }
            });
        }
    });
}
</script> 