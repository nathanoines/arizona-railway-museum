from flask import Flask, render_template, jsonify, request
import json
import os

app = Flask(__name__)

DATA_FILE = 'data/museum_map.json'

def load_data():
    """Load museum map data from JSON file"""
    if os.path.exists(DATA_FILE):
        with open(DATA_FILE, 'r') as f:
            return json.load(f)
    return {
        'tracks': [],
        'trainCars': [],
        'features': []
    }

def save_data(data):
    """Save museum map data to JSON file"""
    os.makedirs('data', exist_ok=True)
    with open(DATA_FILE, 'w') as f:
        json.dump(data, f, indent=2)

@app.route('/')
def index():
    """Serve the main page"""
    return render_template('index.html')

@app.route('/api/map', methods=['GET'])
def get_map():
    """Get current map data"""
    return jsonify(load_data())

@app.route('/api/map', methods=['POST'])
def save_map():
    """Save map data"""
    data = request.json
    save_data(data)
    return jsonify({'status': 'success'})

if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)
