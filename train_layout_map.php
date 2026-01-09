<?php
$page_title = "Train Car Layout Map | Arizona Railway Museum";
require_once __DIR__ . '/assets/header.php';
?>

<style>
.map-container {
    background: #e8ebe8;
    padding: 2rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    box-shadow: inset 0 0 20px rgba(0,0,0,0.1);
    position: relative;
    overflow-x: auto;
}

.map-viewport {
    min-width: 800px;
    position: relative;
}

.track {
    position: relative;
    height: 60px;
    margin: 30px 0;
}

.track-rails {
    position: absolute;
    width: 100%;
    height: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: linear-gradient(to bottom, 
        #666 0%, 
        #999 2px, 
        #666 4px,
        transparent 4px,
        transparent 5px,
        #666 5px,
        #999 7px,
        #666 8px
    );
}

.track-label {
    position: absolute;
    left: -80px;
    top: 50%;
    transform: translateY(-50%);
    font-weight: bold;
    color: #333;
    font-size: 0.9rem;
    width: 70px;
    text-align: right;
}

.train-car {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    height: 50px;
    min-width: 120px;
    border-radius: 3px;
    border: 2px solid #333;
    box-shadow: 0 3px 6px rgba(0,0,0,0.3);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 0.25rem;
    cursor: move;
    transition: transform 0.2s;
    user-select: none;
}

.train-car:hover {
    transform: translateY(-50%) scale(1.05);
    z-index: 10;
}

.train-car.dragging {
    opacity: 0.5;
    z-index: 100;
}

.train-car.passenger {
    background: #4285f4;
    border-color: #1967d2;
}

.train-car.freight {
    background: #8d6e63;
    border-color: #5d4037;
}

.train-car.caboose {
    background: #ea4335;
    border-color: #c5221f;
}

.train-car.locomotive {
    background: #34a853;
    border-color: #1e8e3e;
}

.train-car-name {
    font-weight: bold;
    font-size: 0.75rem;
    color: white;
    text-align: center;
    line-height: 1.1;
}

.train-car-number {
    font-size: 0.65rem;
    color: rgba(255,255,255,0.9);
    text-align: center;
}

.map-info {
    position: absolute;
    top: 10px;
    right: 10px;
    background: white;
    padding: 1rem;
    border-radius: 4px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    font-size: 0.85rem;
}

.compass {
    position: absolute;
    bottom: 20px;
    left: 20px;
    width: 60px;
    height: 60px;
    background: white;
    border-radius: 50%;
    box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    border: 2px solid #333;
}

.compass::before {
    content: "N";
    position: absolute;
    top: 5px;
    color: #ea4335;
    font-size: 1.2rem;
}

.instructions {
    background: #e3f2fd;
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 2rem;
}

.legend {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
    background: white;
    padding: 1rem;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.legend-color {
    width: 40px;
    height: 25px;
    border-radius: 3px;
    border: 2px solid #333;
}
</style>

<div class="grid-x grid-margin-x">
    <div class="small-12 cell">
        <h1>Train Car Layout Map</h1>
        <p class="lead">Visual representation of the museum's train car arrangement</p>
    </div>
</div>

<div class="callout primary instructions">
    <h4>How to Edit This Map</h4>
    <p><strong>To update the layout:</strong> Edit the <code>train_layout_map.php</code> file in your site root. 
    Add or remove train cars by modifying the HTML in the track sections below. Copy and paste the car div blocks 
    and update the reporting mark, number, and type information.</p>
    <p><strong>Car Types:</strong> Change the class to <code>train-car passenger</code>, <code>train-car freight</code>, 
    <code>train-car locomotive</code>, or <code>train-car caboose</code> to change the color.</p>
</div>

<div class="legend">
    <div class="legend-item">
        <div class="legend-color" style="background: #34a853; border-color: #1e8e3e;"></div>
        <span>Locomotive</span>
    </div>
    <div class="legend-item">
        <div class="legend-color" style="background: #4285f4; border-color: #1967d2;"></div>
        <span>Passenger Car</span>
    </div>
    <div class="legend-item">
        <div class="legend-color" style="background: #8d6e63; border-color: #5d4037;"></div>
        <span>Freight Car</span>
    </div>
    <div class="legend-item">
        <div class="legend-color" style="background: #ea4335; border-color: #c5221f;"></div>
        <span>Caboose</span>
    </div>
</div>

<div class="map-container">
    <div class="map-viewport" style="padding-left: 100px;">
        
        <div class="map-info">
            <strong>Museum Yard Layout</strong><br>
            330 E. Ryan Road<br>
            Chandler, AZ
        </div>
        
        <div class="compass"></div>
        
        <!-- Track 1 - Main Display Line -->
        <div class="track">
            <div class="track-label">Track 1</div>
            <div class="track-rails"></div>
            
            <div class="train-car passenger" style="left: 20px;">
                <div class="train-car-name">Denehotso</div>
                <div class="train-car-number">ATSF 3197</div>
            </div>
            
            <div class="train-car passenger" style="left: 150px;">
                <div class="train-car-name">Vista Canyon</div>
                <div class="train-car-number">ATSF 507</div>
            </div>
            
            <div class="train-car passenger" style="left: 280px;">
                <div class="train-car-name">Plaza Taos</div>
                <div class="train-car-number">ATSF 550</div>
            </div>
            
            <div class="train-car passenger" style="left: 410px;">
                <div class="train-car-name">Hi-Level Coach</div>
                <div class="train-car-number">ATSF 725</div>
            </div>
            
            <div class="train-car passenger" style="left: 540px;">
                <div class="train-car-name">Hi-Level Lounge</div>
                <div class="train-car-number">ATSF 575</div>
            </div>
        </div>
        
        <!-- Track 2 - Additional Display -->
        <div class="track">
            <div class="track-label">Track 2</div>
            <div class="track-rails"></div>
            
            <div class="train-car caboose" style="left: 20px; min-width: 80px;">
                <div class="train-car-name">Caboose</div>
                <div class="train-car-number">ATSF 999545</div>
            </div>
            
            <div class="train-car passenger" style="left: 110px;">
                <div class="train-car-name">Pullman Sleeper</div>
                <div class="train-car-number">PRR 4532</div>
            </div>
            
            <div class="train-car caboose" style="left: 240px; min-width: 80px;">
                <div class="train-car-name">Caboose</div>
                <div class="train-car-number">SP 1234</div>
            </div>
        </div>
        
        <!-- Track 3 - Storage/Restoration -->
        <div class="track">
            <div class="track-label">Track 3</div>
            <div class="track-rails"></div>
            
            <div class="train-car freight" style="left: 20px;">
                <div class="train-car-name">Boxcar</div>
                <div class="train-car-number">UP 12345</div>
            </div>
            
            <div class="train-car passenger" style="left: 150px;">
                <div class="train-car-name">Coach Car</div>
                <div class="train-car-number">ATSF 2901</div>
            </div>
        </div>
        
        <!-- Track 4 -->
        <div class="track">
            <div class="track-label">Track 4</div>
            <div class="track-rails"></div>
        </div>
        
        <!-- Track 5 -->
        <div class="track">
            <div class="track-label">Track 5</div>
            <div class="track-rails"></div>
        </div>
        
        <!-- Track 6 -->
        <div class="track">
            <div class="track-label">Track 6</div>
            <div class="track-rails"></div>
        </div>
        
        <!-- Track 7 -->
        <div class="track">
            <div class="track-label">Track 7</div>
            <div class="track-rails"></div>
        </div>
        
    </div>
</div>

<div class="callout secondary">
    <h4>Editing the Map</h4>
    <p><strong>To add a new car:</strong> Copy one of the train-car div blocks and paste it in the desired track. Update the car name, number, and adjust the <code>left</code> position (in pixels) to place it along the track.</p>
    <p><strong>To move a car:</strong> Change the <code>style="left: XXXpx;"</code> value to reposition it horizontally along the track.</p>
    <p><strong>Car spacing:</strong> Cars are typically 140px apart (120px car width + 20px gap). Cabooses are smaller at 80px.</p>
    <pre style="background: #fff; padding: 1rem; border-radius: 4px; overflow-x: auto;"><code>&lt;div class="train-car passenger" style="left: 50px;"&gt;
    &lt;div class="train-car-name"&gt;Your Car Name&lt;/div&gt;
    &lt;div class="train-car-number"&gt;ROAD 1234&lt;/div&gt;
&lt;/div&gt;</code></pre>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const trainCars = document.querySelectorAll('.train-car');
    const tracks = document.querySelectorAll('.track');
    let draggedCar = null;
    let offsetX = 0;
    let offsetY = 0;
    let originalParent = null;
    let originalLeft = null;
    
    trainCars.forEach(car => {
        car.addEventListener('mousedown', startDrag);
        car.addEventListener('touchstart', startDrag, { passive: false });
    });
    
    function startDrag(e) {
        draggedCar = this;
        draggedCar.classList.add('dragging');
        
        // Store original position
        originalParent = draggedCar.parentElement;
        originalLeft = draggedCar.style.left;
        
        const rect = draggedCar.getBoundingClientRect();
        
        if (e.type === 'mousedown') {
            offsetX = e.clientX - rect.left;
            offsetY = e.clientY - rect.top;
        } else {
            e.preventDefault();
            offsetX = e.touches[0].clientX - rect.left;
            offsetY = e.touches[0].clientY - rect.top;
        }
        
        // Make position fixed while dragging
        draggedCar.style.position = 'fixed';
        draggedCar.style.zIndex = '1000';
        
        document.addEventListener('mousemove', drag);
        document.addEventListener('mouseup', stopDrag);
        document.addEventListener('touchmove', drag, { passive: false });
        document.addEventListener('touchend', stopDrag);
    }
    
    function drag(e) {
        if (!draggedCar) return;
        
        e.preventDefault();
        
        let clientX, clientY;
        if (e.type === 'mousemove') {
            clientX = e.clientX;
            clientY = e.clientY;
        } else {
            clientX = e.touches[0].clientX;
            clientY = e.touches[0].clientY;
        }
        
        draggedCar.style.left = (clientX - offsetX) + 'px';
        draggedCar.style.top = (clientY - offsetY) + 'px';
        draggedCar.style.transform = 'none';
    }
    
    function stopDrag(e) {
        if (!draggedCar) return;
        
        let clientX, clientY;
        if (e.type === 'mouseup') {
            clientX = e.clientX;
            clientY = e.clientY;
        } else {
            clientX = e.changedTouches[0].clientX;
            clientY = e.changedTouches[0].clientY;
        }
        
        // Find which track we're over
        let targetTrack = null;
        let closestDistance = Infinity;
        
        tracks.forEach(track => {
            const rect = track.getBoundingClientRect();
            const trackCenterY = rect.top + rect.height / 2;
            const distance = Math.abs(clientY - trackCenterY);
            
            if (distance < closestDistance && distance < 60) {
                closestDistance = distance;
                targetTrack = track;
            }
        });
        
        if (targetTrack) {
            // Move to new track
            const trackRect = targetTrack.getBoundingClientRect();
            const viewportRect = targetTrack.parentElement.getBoundingClientRect();
            let newLeft = clientX - viewportRect.left - offsetX;
            
            // Keep within bounds
            const minLeft = 0;
            const maxLeft = trackRect.width - draggedCar.offsetWidth;
            newLeft = Math.max(minLeft, Math.min(newLeft, maxLeft));
            
            // Check for collisions with other cars on this track
            const carsOnTrack = Array.from(targetTrack.querySelectorAll('.train-car'));
            const draggedWidth = draggedCar.offsetWidth;
            const padding = 10; // Minimum gap between cars
            
            // Sort cars by position
            const sortedCars = carsOnTrack
                .filter(car => car !== draggedCar)
                .map(car => ({
                    element: car,
                    left: parseInt(car.style.left) || 0,
                    width: car.offsetWidth
                }))
                .sort((a, b) => a.left - b.left);
            
            // Find a valid position - check if newLeft fits in gaps between cars
            let finalLeft = newLeft;
            let foundSpace = true;
            
            // Check if position conflicts with any car
            for (const car of sortedCars) {
                const overlap = finalLeft < car.left + car.width + padding && 
                               finalLeft + draggedWidth + padding > car.left;
                
                if (overlap) {
                    foundSpace = false;
                    // Try left side first
                    const leftOption = car.left - draggedWidth - padding;
                    const rightOption = car.left + car.width + padding;
                    
                    // Check if left side is valid and free
                    if (leftOption >= minLeft) {
                        let leftIsFree = true;
                        for (const other of sortedCars) {
                            if (other === car) continue;
                            if (leftOption < other.left + other.width + padding && 
                                leftOption + draggedWidth + padding > other.left) {
                                leftIsFree = false;
                                break;
                            }
                        }
                        if (leftIsFree) {
                            finalLeft = leftOption;
                            foundSpace = true;
                            break;
                        }
                    }
                    
                    // Try right side
                    if (rightOption + draggedWidth <= maxLeft) {
                        let rightIsFree = true;
                        for (const other of sortedCars) {
                            if (other === car) continue;
                            if (rightOption < other.left + other.width + padding && 
                                rightOption + draggedWidth + padding > other.left) {
                                rightIsFree = false;
                                break;
                            }
                        }
                        if (rightIsFree) {
                            finalLeft = rightOption;
                            foundSpace = true;
                            break;
                        }
                    }
                    break;
                }
            }
            
            newLeft = Math.max(minLeft, Math.min(maxLeft, finalLeft));
            
            targetTrack.appendChild(draggedCar);
            draggedCar.style.position = 'absolute';
            draggedCar.style.left = newLeft + 'px';
            draggedCar.style.top = '';
            draggedCar.style.transform = 'translateY(-50%)';
        } else {
            // Return to original position
            if (originalParent) {
                originalParent.appendChild(draggedCar);
            }
            draggedCar.style.position = 'absolute';
            draggedCar.style.left = originalLeft;
            draggedCar.style.top = '';
            draggedCar.style.transform = 'translateY(-50%)';
        }
        
        draggedCar.classList.remove('dragging');
        draggedCar.style.zIndex = '';
        draggedCar = null;
        
        document.removeEventListener('mousemove', drag);
        document.removeEventListener('mouseup', stopDrag);
        document.removeEventListener('touchmove', drag);
        document.removeEventListener('touchend', stopDrag);
    }
});
</script>

<?php require_once __DIR__ . '/assets/footer.php'; ?>
