// Global state
let isAdminMode = true;
let tracks = [];
let trainCars = [];
let features = [];
let selectedItem = null;
let draggingItem = null;
let drawingTrack = false;
let currentTrackPoints = [];

// Canvas setup
const canvas = document.getElementById('mapCanvas');
const ctx = canvas.getContext('2d');

// Constants
const SNAP_DISTANCE = 30;
const TRACK_WIDTH = 4;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadMapData();
    setupEventListeners();
    initAdminMode();
    createDefaultTracks();
    render();
});

// Event Listeners
function setupEventListeners() {
    document.getElementById('saveBtn').addEventListener('click', saveMapData);
    document.getElementById('drawTrackBtn').addEventListener('click', startDrawingTrack);
    document.getElementById('clearTracksBtn').addEventListener('click', clearTracks);
    document.getElementById('defaultTracksBtn').addEventListener('click', createDefaultTracks);
    document.getElementById('addCarBtn').addEventListener('click', addTrainCar);
    document.getElementById('addBathroomBtn').addEventListener('click', () => addFeature('bathroom'));
    document.getElementById('addVendorBtn').addEventListener('click', () => addFeature('vendor'));

    canvas.addEventListener('mousedown', handleMouseDown);
    canvas.addEventListener('mousemove', handleMouseMove);
    canvas.addEventListener('mouseup', handleMouseUp);
    canvas.addEventListener('click', handleClick);

    document.querySelector('.close').addEventListener('click', closeModal);
    document.getElementById('saveDetailsBtn').addEventListener('click', saveItemDetails);
    document.getElementById('deleteItemBtn').addEventListener('click', deleteItem);
}

// Initialize Admin Mode UI
function initAdminMode() {
    const adminTools = document.getElementById('adminTools');
    const modeIndicator = document.getElementById('modeIndicator');
    
    adminTools.style.display = 'block';
    modeIndicator.textContent = 'ADMIN MODE';
    modeIndicator.classList.add('admin');
}

// Track Management
function createDefaultTracks() {
    tracks = [];

    // Main horizontal rail
    const mainY = 400;
    tracks.push({
        points: [{x: 50, y: mainY}, {x: 1350, y: mainY}],
        type: 'main'
    });

    // 7 branch rails (dead-end spurs)
    const branchStartX = 200;
    const branchSpacing = 180;
    const branchLength = 200;

    for (let i = 0; i < 7; i++) {
        const x = branchStartX + (i * branchSpacing);
        tracks.push({
            points: [
                {x: x, y: mainY},
                {x: x, y: mainY - branchLength}
            ],
            type: 'branch'
        });
    }

    render();
}

function startDrawingTrack() {
    drawingTrack = true;
    currentTrackPoints = [];
    canvas.style.cursor = 'crosshair';
}

function clearTracks() {
    if (confirm('Clear all tracks?')) {
        tracks = [];
        render();
    }
}

// Train Car Management
function addTrainCar() {
    const length = parseInt(document.getElementById('carLength').value) || 80;
    const car = {
        id: Date.now(),
        x: 700,
        y: 400,
        length: length,
        width: 30,
        name: 'New Train Car',
        description: '',
        type: 'trainCar'
    };
    trainCars.push(car);
    render();
}

// Feature Management
function addFeature(type) {
    const feature = {
        id: Date.now(),
        x: 400,
        y: 600,
        width: 60,
        height: 60,
        type: type,
        name: type === 'bathroom' ? 'Bathroom' : 'Vendor Stall',
        description: ''
    };
    features.push(feature);
    render();
}

// Mouse Handlers
function handleMouseDown(e) {
    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    if (!isAdminMode) return;

    if (drawingTrack) {
        currentTrackPoints.push({x, y});
        if (currentTrackPoints.length >= 2) {
            tracks.push({
                points: [...currentTrackPoints],
                type: 'custom'
            });
            currentTrackPoints = [];
            drawingTrack = false;
            canvas.style.cursor = 'default';
        }
        render();
        return;
    }

    // Check if clicking on a train car
    for (let car of trainCars) {
        if (isPointInRect(x, y, car.x - car.length/2, car.y - car.width/2, car.length, car.width)) {
            draggingItem = car;
            draggingItem.offsetX = x - car.x;
            draggingItem.offsetY = y - car.y;
            return;
        }
    }

    // Check if clicking on a feature
    for (let feature of features) {
        if (isPointInRect(x, y, feature.x, feature.y, feature.width, feature.height)) {
            draggingItem = feature;
            draggingItem.offsetX = x - feature.x;
            draggingItem.offsetY = y - feature.y;
            return;
        }
    }
}

function handleMouseMove(e) {
    if (!draggingItem) return;

    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    if (draggingItem.type === 'trainCar') {
        // Try to snap to track
        const snapPoint = findNearestTrackPoint(x, y);
        if (snapPoint) {
            draggingItem.x = snapPoint.x;
            draggingItem.y = snapPoint.y;
        } else {
            draggingItem.x = x - draggingItem.offsetX;
            draggingItem.y = y - draggingItem.offsetY;
        }
    } else {
        // Free movement for features
        draggingItem.x = x - draggingItem.offsetX;
        draggingItem.y = y - draggingItem.offsetY;
    }

    render();
}

function handleMouseUp(e) {
    draggingItem = null;
}

function handleClick(e) {
    if (isAdminMode) return; // Only show details in view mode

    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    // Check train cars
    for (let car of trainCars) {
        if (isPointInRect(x, y, car.x - car.length/2, car.y - car.width/2, car.length, car.width)) {
            showDetails(car);
            return;
        }
    }

    // Check features
    for (let feature of features) {
        if (isPointInRect(x, y, feature.x, feature.y, feature.width, feature.height)) {
            showDetails(feature);
            return;
        }
    }
}

// Helper Functions
function isPointInRect(px, py, rx, ry, rw, rh) {
    return px >= rx && px <= rx + rw && py >= ry && py <= ry + rh;
}

function findNearestTrackPoint(x, y) {
    let nearest = null;
    let minDist = SNAP_DISTANCE;

    for (let track of tracks) {
        for (let i = 0; i < track.points.length - 1; i++) {
            const p1 = track.points[i];
            const p2 = track.points[i + 1];

            // Check if point is near this track segment
            const dist = distanceToSegment(x, y, p1.x, p1.y, p2.x, p2.y);
            if (dist < minDist) {
                minDist = dist;
                nearest = nearestPointOnSegment(x, y, p1.x, p1.y, p2.x, p2.y);
            }
        }
    }

    return nearest;
}

function distanceToSegment(px, py, x1, y1, x2, y2) {
    const A = px - x1;
    const B = py - y1;
    const C = x2 - x1;
    const D = y2 - y1;

    const dot = A * C + B * D;
    const lenSq = C * C + D * D;
    let param = -1;

    if (lenSq !== 0) param = dot / lenSq;

    let xx, yy;

    if (param < 0) {
        xx = x1;
        yy = y1;
    } else if (param > 1) {
        xx = x2;
        yy = y2;
    } else {
        xx = x1 + param * C;
        yy = y1 + param * D;
    }

    const dx = px - xx;
    const dy = py - yy;
    return Math.sqrt(dx * dx + dy * dy);
}

function nearestPointOnSegment(px, py, x1, y1, x2, y2) {
    const A = px - x1;
    const B = py - y1;
    const C = x2 - x1;
    const D = y2 - y1;

    const dot = A * C + B * D;
    const lenSq = C * C + D * D;
    let param = -1;

    if (lenSq !== 0) param = dot / lenSq;

    if (param < 0) {
        return {x: x1, y: y1};
    } else if (param > 1) {
        return {x: x2, y: y2};
    } else {
        return {x: x1 + param * C, y: y1 + param * D};
    }
}

// Modal Functions
function showDetails(item) {
    selectedItem = item;
    document.getElementById('itemName').value = item.name || '';
    document.getElementById('itemDescription').value = item.description || '';

    const lengthInput = document.getElementById('itemLength');
    if (item.type === 'trainCar') {
        lengthInput.value = item.length;
        lengthInput.parentElement.style.display = 'block';
    } else {
        lengthInput.parentElement.style.display = 'none';
    }

    // Only show delete button in admin mode
    document.getElementById('deleteItemBtn').style.display = isAdminMode ? 'inline-block' : 'none';
    document.getElementById('saveDetailsBtn').style.display = isAdminMode ? 'inline-block' : 'none';

    document.getElementById('detailsModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('detailsModal').style.display = 'none';
    selectedItem = null;
}

function saveItemDetails() {
    if (!selectedItem || !isAdminMode) return;

    selectedItem.name = document.getElementById('itemName').value;
    selectedItem.description = document.getElementById('itemDescription').value;

    if (selectedItem.type === 'trainCar') {
        selectedItem.length = parseInt(document.getElementById('itemLength').value);
    }

    closeModal();
    render();
}

function deleteItem() {
    if (!selectedItem || !isAdminMode) return;

    if (selectedItem.type === 'trainCar') {
        trainCars = trainCars.filter(c => c.id !== selectedItem.id);
    } else {
        features = features.filter(f => f.id !== selectedItem.id);
    }

    closeModal();
    render();
}

// Rendering
function render() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Draw tracks
    ctx.strokeStyle = '#34495e';
    ctx.lineWidth = TRACK_WIDTH;

    for (let track of tracks) {
        ctx.beginPath();
        ctx.moveTo(track.points[0].x, track.points[0].y);
        for (let i = 1; i < track.points.length; i++) {
            ctx.lineTo(track.points[i].x, track.points[i].y);
        }
        ctx.stroke();

        // Draw railroad ties
        for (let i = 0; i < track.points.length - 1; i++) {
            const p1 = track.points[i];
            const p2 = track.points[i + 1];
            drawRailroadTies(p1.x, p1.y, p2.x, p2.y);
        }
    }

    // Draw current track being drawn
    if (drawingTrack && currentTrackPoints.length > 0) {
        ctx.strokeStyle = '#e74c3c';
        ctx.lineWidth = 2;
        ctx.setLineDash([5, 5]);
        ctx.beginPath();
        ctx.moveTo(currentTrackPoints[0].x, currentTrackPoints[0].y);
        for (let i = 1; i < currentTrackPoints.length; i++) {
            ctx.lineTo(currentTrackPoints[i].x, currentTrackPoints[i].y);
        }
        ctx.stroke();
        ctx.setLineDash([]);
    }

    // Draw train cars
    for (let car of trainCars) {
        ctx.fillStyle = '#c0392b';
        ctx.strokeStyle = '#7f2318';
        ctx.lineWidth = 2;

        const x = car.x - car.length / 2;
        const y = car.y - car.width / 2;

        ctx.fillRect(x, y, car.length, car.width);
        ctx.strokeRect(x, y, car.length, car.width);

        // Draw label
        ctx.fillStyle = 'white';
        ctx.font = '12px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(car.name, car.x, car.y + 5);
    }

    // Draw features
    for (let feature of features) {
        if (feature.type === 'bathroom') {
            ctx.fillStyle = '#3498db';
        } else if (feature.type === 'vendor') {
            ctx.fillStyle = '#f39c12';
        }

        ctx.fillRect(feature.x, feature.y, feature.width, feature.height);
        ctx.strokeStyle = '#2c3e50';
        ctx.lineWidth = 2;
        ctx.strokeRect(feature.x, feature.y, feature.width, feature.height);

        // Draw label
        ctx.fillStyle = 'white';
        ctx.font = '10px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(feature.name, feature.x + feature.width/2, feature.y + feature.height/2 + 3);
    }
}

function drawRailroadTies(x1, y1, x2, y2) {
    const dx = x2 - x1;
    const dy = y2 - y1;
    const length = Math.sqrt(dx * dx + dy * dy);
    const steps = Math.floor(length / 20);

    ctx.strokeStyle = '#7f8c8d';
    ctx.lineWidth = 2;

    for (let i = 0; i <= steps; i++) {
        const t = i / steps;
        const x = x1 + dx * t;
        const y = y1 + dy * t;

        // Perpendicular line
        const angle = Math.atan2(dy, dx) + Math.PI / 2;
        const tieLength = 15;

        ctx.beginPath();
        ctx.moveTo(x + Math.cos(angle) * tieLength, y + Math.sin(angle) * tieLength);
        ctx.lineTo(x - Math.cos(angle) * tieLength, y - Math.sin(angle) * tieLength);
        ctx.stroke();
    }
}

// Data Persistence
async function loadMapData() {
    try {
        const response = await fetch('/api/map');
        const data = await response.json();

        if (data.tracks && data.tracks.length > 0) {
            tracks = data.tracks;
            trainCars = data.trainCars || [];
            features = data.features || [];
        } else {
            createDefaultTracks();
        }

        render();
    } catch (error) {
        console.error('Error loading map data:', error);
        createDefaultTracks();
    }
}

async function saveMapData() {
    try {
        const data = {
            tracks: tracks,
            trainCars: trainCars,
            features: features
        };

        const response = await fetch('/api/map', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (response.ok) {
            alert('Map saved successfully!');
        } else {
            alert('Error saving map');
        }
    } catch (error) {
        console.error('Error saving map data:', error);
        alert('Error saving map');
    }
}
