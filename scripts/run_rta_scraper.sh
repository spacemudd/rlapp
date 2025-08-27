#!/bin/bash

# Navigate to the scripts directory
cd "$(dirname "$0")"

# Activate virtual environment and run the scraper
source venv/bin/activate
python scrap_rta.py
