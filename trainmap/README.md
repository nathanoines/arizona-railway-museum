# Interactive Train Museum Map

A simple web application for managing and displaying an interactive train museum map. Features drag-and-drop train cars, customizable track layouts, and museum facilities.

## Features

- **View Mode**: Browse the museum map, click on train cars and features to see details
- **Admin Mode**: Full editing capabilities (unsecured for now)
  - Draw custom track layouts or use default configuration
  - Add and position train cars with custom lengths
  - Add museum features (bathrooms, vendor stalls)
  - Drag and drop train cars (auto-snap to tracks)
  - Drag and drop features (free positioning)
  - Edit names and descriptions
  - Save/load map state

## Default Track Layout

- 1 main horizontal rail
- 7 branch rails (dead-end spurs) extending from the main rail

## Setup Instructions

### Prerequisites

- Python 3.7 or higher

### Installation

1. Install Python dependencies:
```bash
pip install -r requirements.txt
```

### Running the Application

1. Start the Flask server:
```bash
python app.py
```

2. Open your browser and navigate to:
```
http://localhost:5000
```

### Using Docker (Optional)

If you prefer to use Docker for network routing:

```bash
docker build -t trainmap .
docker run -p 5000:5000 trainmap
```

## Usage

### View Mode (Default)

- Click on train cars or features to view their details
- Browse the museum layout

### Admin Mode

1. Click "Enter Admin Mode" button
2. Use the admin tools panel to:
   - **Draw Track**: Click to start drawing custom tracks (click twice to create a segment)
   - **Clear All Tracks**: Remove all tracks
   - **Reset to Default**: Restore the default 1 main + 7 branch track layout
   - **Add Train Car**: Add a new train car (adjust length before adding)
   - **Add Bathroom/Vendor**: Add museum features
3. Click and drag train cars - they will snap to nearby tracks
4. Click and drag features for free-form positioning
5. Click items to edit their properties (name, description, length)
6. Click "Save Changes" to persist your map

### Data Storage

Map data is saved to `data/museum_map.json` and includes:
- Track layouts (points and segments)
- Train car positions, lengths, and details
- Feature positions and details

## Customization

### Canvas Size

Edit [static/map.js:437](static/map.js#L437) to change canvas dimensions.

### Track Appearance

Modify the rendering functions in [static/map.js](static/map.js) to customize track colors, widths, and railroad tie spacing.

### Adding New Feature Types

1. Add a new button in [templates/index.html](templates/index.html)
2. Add event listener and handling in [static/map.js](static/map.js)
3. Add rendering logic with custom colors/shapes

## Browser Compatibility

Works in all modern browsers that support HTML5 Canvas.

## Future Enhancements

- User authentication for admin mode
- Multiple map saves/versions
- Image uploads for custom features
- Zoom and pan controls
- Measurement tools
- Print/export functionality
