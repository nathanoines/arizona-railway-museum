<?php
$page_title = "Equipment Details | Arizona Railway Museum";
require_once __DIR__ . '/../assets/header.php';
require_once __DIR__ . '/../config/db.php';
$pdo = getDbConnection();

// Validate and capture the ID
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(404);
    die('Invalid equipment ID.');
}
$equipmentId = (int)$_GET['id'];

// Get DB row
$stmt = $pdo->prepare("
    SELECT id, reporting_mark, road_number, equipment_type, builder, built_year,
           status, notes, audio_file
    FROM equipment
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $equipmentId]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    die('Equipment not found.');
}
?>

<div class="grid-x grid-margin-x">
    <div class="small-12 cell">
        <div class="arm-nav-btn-container" style="margin-bottom: 1.5rem;">
            <a href="/equipment/" class="arm-nav-link secondary">
                <span style="font-size: 1.1rem;">&larr;</span> Back to List
            </a>
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <span style="color: #ddd;">|</span>
                <a href="/admin/equipment/edit/<?php echo $equipmentId; ?>" class="arm-nav-link primary">
                    <span style="font-size: 0.95rem;">&#9998;</span> Edit
                </a>
            <?php endif; ?>
        </div>
        <h1><?php echo htmlspecialchars($item['reporting_mark'] . ' ' . $item['road_number']); ?></h1>
        <p class="lead"><?php echo htmlspecialchars($item['equipment_type']); ?></p>
    </div>
</div>

<div class="grid-x grid-margin-x">
    <!-- Equipment Details -->
    <div class="small-12 medium-7 cell">
        <div class="card arm-card">
            <div class="card-section">
                <h3>🚂 Equipment Details</h3>
                
                <div style="margin-bottom: 1rem;">
                    <strong>Company/Railroad:</strong><br>
                    <span style="color: #1779ba; font-size: 1.25rem; font-weight: 600;">
                        <?php echo htmlspecialchars($item['reporting_mark']); ?>
                    </span>
                </div>

                <div style="margin-bottom: 1rem;">
                    <strong>Number/Identifier:</strong><br>
                    <span style="font-size: 1.1rem; font-weight: 600;">
                        <?php echo htmlspecialchars($item['road_number']); ?>
                    </span>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <strong>Equipment Type:</strong><br>
                    <?php echo htmlspecialchars($item['equipment_type']); ?>
                </div>
                
                <?php if (!empty($item['builder'])): ?>
                <div style="margin-bottom: 1rem;">
                    <strong>Builder:</strong><br>
                    <?php echo htmlspecialchars($item['builder']); ?>
                </div>
                <?php endif; ?>
                
                <div style="margin-bottom: 1rem;">
                    <strong>Built Year:</strong><br>
                    <?php echo htmlspecialchars($item['built_year'] ?? 'Unknown'); ?>
                </div>
                
                <div style="margin-bottom: 0;">
                    <strong>Current Status:</strong><br>
                    <span class="label secondary"><?php echo htmlspecialchars($item['status'] ?? 'Unknown'); ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($item['audio_file'])): ?>
        <!-- Audio Narration Card (Mobile Only - shown here) -->
        <div class="card arm-card show-for-small-only" style="margin-top: 1rem;">
            <div class="card-section">
                <h3>🔊 Audio Narration</h3>
                <p style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;">Listen to the history and details of this equipment</p>
                <audio controls style="width: 100%;">
                    <source src="/audio/<?php echo htmlspecialchars($item['audio_file'], ENT_QUOTES, 'UTF-8'); ?>" type="audio/mpeg">
                    Your browser does not support the audio element.
                </audio>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($item['notes'])): ?>
        <div class="card arm-card" style="margin-top: 1rem;">
            <div class="card-section">
                <h3>📖 History & Notes</h3>
                <div style="line-height: 1.6;">
                    <?php 
                    // CKEditor saves HTML, so output it directly but strip unwanted classes
                    $cleanNotes = preg_replace('/class="[^"]*"/', '', $item['notes']);
                    echo $cleanNotes;
                    ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Photos Section -->
    <?php
    // Check for photos in /arm/images/equipment/{id}/ directory
    $photoDir = __DIR__ . '/../images/equipment/' . $equipmentId;
    $photoWebPath = '\images/equipment/' . $equipmentId;
    $mainPhoto = $photoDir . '/main.jpg';
    $hasPhotos = false;
    
    // Load captions from JSON file
    $captionsFile = $photoDir . '/captions.json';
    $captions = [];
    if (file_exists($captionsFile)) {
        $captionsJson = file_get_contents($captionsFile);
        $captions = json_decode($captionsJson, true) ?? [];
    }
    
    // Collect all available photos
    $photos = [];
    if (file_exists($mainPhoto)) {
        $photos[] = $photoWebPath . '/main.jpg';
        $hasPhotos = true;
    }
    
    // Check for additional photos (photo-1.jpg, photo-2.jpg, etc.)
    $photoIndex = 1;
    while (file_exists($photoDir . '/photo-' . $photoIndex . '.jpg')) {
        $photos[] = $photoWebPath . '/photo-' . $photoIndex . '.jpg';
        $photoIndex++;
        $hasPhotos = true;
    }
    ?>
    
    <div class="small-12 medium-5 cell">
        <?php if ($hasPhotos): ?>
        <div class="card arm-card">
            <div class="card-section">
                <h3>📷 Photos</h3>
                    <!-- Slideshow Container -->
                    <div id="photoSlideshow" style="position: relative; margin-bottom: 1rem;">
                        <div style="position: relative;">
                            <img id="slideshowImage" 
                                 src="<?php echo htmlspecialchars($photos[0], ENT_QUOTES, 'UTF-8'); ?>" 
                                 alt="<?php echo htmlspecialchars($item['reporting_mark'] . ' ' . $item['road_number']); ?>"
                                 style="width: 100%; border-radius: 4px 4px 0 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; display: block;"
                                 onclick="openLightbox(0)">
                            
                            <!-- Photo Caption (only shown if caption exists) -->
                            <?php 
                            $firstPhotoName = basename($photos[0]);
                            $firstCaption = $captions[$firstPhotoName] ?? '';
                            // Strip HTML tags from caption
                            $firstCaptionText = strip_tags($firstCaption);
                            ?>
                            <div id="photoCaption" style="<?php echo !empty($firstCaptionText) ? '' : 'display: none; '; ?>background: rgba(0,0,0,0.85); color: white; padding: 10px 12px; border-radius: 0 0 4px 4px; font-size: 14px; line-height: 1.4; margin: 0;">
                                <?php echo !empty($firstCaptionText) ? htmlspecialchars($firstCaptionText, ENT_QUOTES, 'UTF-8') : ''; ?>
                            </div>
                        </div>
                        
                        <?php if (count($photos) > 1): ?>
                        <!-- Navigation Arrows -->
                        <button onclick="changeSlide(-1)" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: white; border: none; padding: 10px 15px; cursor: pointer; border-radius: 4px; font-size: 18px; z-index: 10;">❮</button>
                        <button onclick="changeSlide(1)" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: white; border: none; padding: 10px 15px; cursor: pointer; border-radius: 4px; font-size: 18px; z-index: 10;">❯</button>
                        
                        <!-- Photo Counter -->
                        <div id="photoCounter" style="position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.7); color: white; padding: 5px 12px; border-radius: 15px; font-size: 14px;">
                            1 / <?php echo count($photos); ?>
                        </div>
                        
                        <!-- Thumbnail Navigation -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 0.5rem; margin-top: 0.75rem;">
                            <?php foreach ($photos as $idx => $photo): ?>
                            <img src="<?php echo htmlspecialchars($photo, ENT_QUOTES, 'UTF-8'); ?>" 
                                 alt="Thumbnail <?php echo $idx + 1; ?>"
                                 onclick="goToSlide(<?php echo $idx; ?>)"
                                 class="thumbnail"
                                 data-index="<?php echo $idx; ?>"
                                 style="width: 100%; height: 80px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 2px solid <?php echo $idx === 0 ? '#1779ba' : 'transparent'; ?>; transition: border-color 0.2s;">
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Lightbox Modal -->
                    <div id="lightbox" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; flex-direction: column; justify-content: center; align-items: center;">
                        <button onclick="closeLightbox()" style="position: absolute; top: 20px; right: 30px; background: none; border: none; color: white; font-size: 40px; cursor: pointer; z-index: 10001;">×</button>
                        
                        <img id="lightboxImage" src="" alt="" style="max-width: 90%; max-height: 80%; object-fit: contain; border-radius: 4px;">
                        
                        <!-- Lightbox Caption -->
                        <div id="lightboxCaption" style="background: rgba(0,0,0,0.8); color: white; padding: 12px 20px; margin-top: 15px; border-radius: 8px; font-size: 16px; max-width: 80%; text-align: center; line-height: 1.5;"></div>
                        
                        <?php if (count($photos) > 1): ?>
                        <button onclick="lightboxChangeSlide(-1)" style="position: absolute; left: 30px; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.3); color: white; border: none; padding: 15px 20px; cursor: pointer; border-radius: 4px; font-size: 24px; z-index: 10001;">❮</button>
                        <button onclick="lightboxChangeSlide(1)" style="position: absolute; right: 30px; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.3); color: white; border: none; padding: 15px 20px; cursor: pointer; border-radius: 4px; font-size: 24px; z-index: 10001;">❯</button>
                        
                        <div id="lightboxCounter" style="position: absolute; top: 30px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.7); color: white; padding: 8px 16px; border-radius: 20px; font-size: 16px;">
                            1 / <?php echo count($photos); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <script>
                    const photos = <?php echo json_encode($photos); ?>;
                    const captions = <?php echo json_encode($captions); ?>;
                    let currentSlide = 0;
                    
                    function getPhotoFilename(photoUrl) {
                        return photoUrl.split('/').pop();
                    }
                    
                    function stripHtmlTags(html) {
                        const temp = document.createElement('div');
                        temp.innerHTML = html;
                        return temp.textContent || temp.innerText || '';
                    }
                    
                    function updateCaption() {
                        const filename = getPhotoFilename(photos[currentSlide]);
                        const caption = captions[filename] || '';
                        const captionEl = document.getElementById('photoCaption');
                        if (caption) {
                            captionEl.textContent = stripHtmlTags(caption);
                            captionEl.style.display = 'block';
                        } else {
                            captionEl.style.display = 'none';
                        }
                    }
                    
                    function changeSlide(direction) {
                        currentSlide = (currentSlide + direction + photos.length) % photos.length;
                        updateSlideshow();
                    }
                    
                    function goToSlide(index) {
                        currentSlide = index;
                        updateSlideshow();
                    }
                    
                    function updateSlideshow() {
                        document.getElementById('slideshowImage').src = photos[currentSlide];
                        updateCaption();
                        <?php if (count($photos) > 1): ?>
                        document.getElementById('photoCounter').textContent = (currentSlide + 1) + ' / ' + photos.length;
                        
                        // Update thumbnail borders
                        document.querySelectorAll('.thumbnail').forEach((thumb, idx) => {
                            thumb.style.borderColor = idx === currentSlide ? '#1779ba' : 'transparent';
                        });
                        <?php endif; ?>
                    }
                    
                    function updateLightboxCaption() {
                        const filename = getPhotoFilename(photos[currentSlide]);
                        const caption = captions[filename] || '';
                        const captionEl = document.getElementById('lightboxCaption');
                        if (caption) {
                            captionEl.textContent = stripHtmlTags(caption);
                            captionEl.style.display = 'block';
                        } else {
                            captionEl.style.display = 'none';
                        }
                    }
                    
                    function openLightbox(index) {
                        currentSlide = index;
                        document.getElementById('lightbox').style.display = 'flex';
                        document.getElementById('lightboxImage').src = photos[currentSlide];
                        updateLightboxCaption();
                        <?php if (count($photos) > 1): ?>
                        document.getElementById('lightboxCounter').textContent = (currentSlide + 1) + ' / ' + photos.length;
                        <?php endif; ?>
                        document.body.style.overflow = 'hidden';
                    }
                    
                    function closeLightbox() {
                        document.getElementById('lightbox').style.display = 'none';
                        document.body.style.overflow = 'auto';
                    }
                    
                    function lightboxChangeSlide(direction) {
                        currentSlide = (currentSlide + direction + photos.length) % photos.length;
                        document.getElementById('lightboxImage').src = photos[currentSlide];
                        updateLightboxCaption();
                        <?php if (count($photos) > 1): ?>
                        document.getElementById('lightboxCounter').textContent = (currentSlide + 1) + ' / ' + photos.length;
                        <?php endif; ?>
                    }
                    
                    // Keyboard navigation
                    document.addEventListener('keydown', function(e) {
                        const lightbox = document.getElementById('lightbox');
                        if (lightbox.style.display === 'flex') {
                            if (e.key === 'Escape') closeLightbox();
                            if (e.key === 'ArrowLeft') lightboxChangeSlide(-1);
                            if (e.key === 'ArrowRight') lightboxChangeSlide(1);
                        }
                    });
                    
                    // Close lightbox when clicking outside image
                    document.getElementById('lightbox').addEventListener('click', function(e) {
                        if (e.target === this) closeLightbox();
                    });
                    </script>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($item['audio_file'])): ?>
        <!-- Audio Narration Card (Desktop/Tablet - shown here in right column) -->
        <div class="card arm-card hide-for-small-only" style="margin-top: 1rem;">
            <div class="card-section">
                <h3>🔊 Audio Narration</h3>
                <p style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;">Listen to the history and details of this equipment</p>
                <audio controls style="width: 100%;">
                    <source src="/audio/<?php echo htmlspecialchars($item['audio_file'], ENT_QUOTES, 'UTF-8'); ?>" type="audio/mpeg">
                    Your browser does not support the audio element.
                </audio>
            </div>
        </div>
        <?php endif; ?>

        <?php
        // Load documents from database
        $docStmt = $pdo->prepare("
            SELECT id, filename, display_name, description, file_type, file_size
            FROM equipment_documents
            WHERE equipment_id = :equipment_id
            ORDER BY sort_order ASC, upload_date DESC
        ");
        $docStmt->execute([':equipment_id' => $equipmentId]);
        $documents = $docStmt->fetchAll();
        ?>

        <?php if (!empty($documents)): ?>
        <!-- Documents Card -->
        <div class="card arm-card" style="margin-top: 1rem;">
            <div class="card-section">
                <h3>📄 Documents & Resources</h3>
                <p style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;">
                    View available documentation, brochures, and resources for this equipment.
                </p>

                <div style="display: grid; gap: 0.75rem;">
                    <?php foreach ($documents as $doc): ?>
                    <div style="padding: 0.75rem; background: #f9f9f9; border-radius: 4px; border-left: 3px solid #1779ba;">
                        <div style="display: flex; align-items: start; gap: 0.75rem;">
                            <!-- File Type Icon -->
                            <div style="flex-shrink: 0; width: 40px; height: 40px; background: #1779ba; color: white; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.7rem; text-transform: uppercase;">
                                <?php echo htmlspecialchars($doc['file_type'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>

                            <!-- Document Info -->
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 600; margin-bottom: 0.25rem; word-break: break-word;">
                                    <?php echo htmlspecialchars($doc['display_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>

                                <?php if (!empty($doc['description'])): ?>
                                <p style="margin: 0 0 0.5rem 0; font-size: 0.85rem; color: #666; word-break: break-word;">
                                    <?php echo htmlspecialchars($doc['description'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                                <?php endif; ?>

                                <div style="font-size: 0.75rem; color: #888;">
                                    <?php echo number_format($doc['file_size'] / 1024, 1); ?> KB
                                </div>
                            </div>

                            <!-- Download Button -->
                            <div style="flex-shrink: 0;">
                                <a href="/documents/equipment/<?php echo $equipmentId; ?>/<?php echo htmlspecialchars($doc['filename'], ENT_QUOTES, 'UTF-8'); ?>"
                                   target="_blank"
                                   class="button tiny primary"
                                   style="margin: 0; white-space: nowrap; border-radius: 8px;">
                                    View
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Info Card -->
        <div class="card arm-card" style="margin-top: 1rem; background: #e3f2fd;">
            <div class="card-section">
                <h3>ℹ️ Visit This Equipment</h3>
                <p>This piece of equipment is on display at the Arizona Railway Museum yard and can be viewed during regular operating hours.</p>
                <div class="arm-nav-btn-container white-bg">
                    <a href="/information" class="arm-nav-link primary">
                        <span>🕐</span> View Hours & Admission
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../assets/footer.php'; ?>
