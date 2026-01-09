<?php
$page_title = "Train Layout Map | Arizona Railway Museum";
require_once __DIR__ . '/../assets/header.php';
?>

<link rel="stylesheet" href="/trainmap/static/style.css">

<div class="grid-x grid-margin-x">
    <div class="small-12 cell">
        <div class="card arm-card">
            <div class="card-section" style="padding: 1rem;">
                <div id="header" style="margin-bottom: 1rem;">
                    <h1 style="margin-bottom: 0.5rem;">Train Layout Map</h1>
                    <button id="saveBtn" class="btn btn-primary">Save Changes</button>
                    <span id="modeIndicator"></span>
                </div>

                <div id="adminTools">
        <div class="tool-section">
            <h3>Track Tools</h3>
            <button id="drawTrackBtn" class="btn">Draw Track</button>
            <button id="clearTracksBtn" class="btn btn-danger">Clear All Tracks</button>
            <button id="defaultTracksBtn" class="btn">Reset to Default</button>
        </div>

        <div class="tool-section">
            <h3>Train Car Tools</h3>
            <button id="addCarBtn" class="btn">Add Train Car</button>
            <label>Car Length: <input type="number" id="carLength" value="80" min="40" max="200" step="10"> px</label>
        </div>

        <div class="tool-section">
            <h3>Feature Tools</h3>
            <button id="addBathroomBtn" class="btn">Add Bathroom</button>
            <button id="addVendorBtn" class="btn">Add Vendor Stall</button>
        </div>
    </div>

                <div id="canvasContainer" style="overflow-x: auto; max-width: 100%; border: 1px solid #ddd; border-radius: 5px;">
                    <canvas id="mapCanvas" width="1400" height="800"></canvas>
                </div>

                <div id="detailsModal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <span class="close">&times;</span>
                        <h2 id="modalTitle">Details</h2>
                        <div id="modalBody">
                            <label>Name: <input type="text" id="itemName"></label>
                            <label>Description: <textarea id="itemDescription" rows="4"></textarea></label>
                            <label>Length (px): <input type="number" id="itemLength" min="40" max="200" step="10"></label>
                            <button id="saveDetailsBtn" class="btn btn-primary">Save</button>
                            <button id="deleteItemBtn" class="btn btn-danger">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/trainmap/static/map.js"></script>

<?php require_once __DIR__ . '/../assets/footer.php'; ?>
