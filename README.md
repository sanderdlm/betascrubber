# Betascrubber

A minimal web app for extracting and reviewing frames from climbing beta videos.

## What it does

1. Enter a YouTube URL of a climbing video
2. Video is downloaded and split into 1-second frames
3. Select the frames you want to keep
4. Scrub through your selection to analyze movement sequences

## Tech Stack

- **Backend**: PHP 8.3 with dependency injection
- **Routing**: FastRoute
- **Templating**: Twig
- **Storage**: DigitalOcean Spaces (S3-compatible)
- **Video Processing**: youtube-dl + ffmpeg

## Setup

### Requirements

- PHP 8.3+
- Composer
- youtube-dl
- ffmpeg
- DigitalOcean Spaces account (or S3)

### Installation

```bash
# Install dependencies
composer install

# Copy environment template
cp .env.example .env

# Add your DigitalOcean Spaces credentials to .env
SPACES_KEY=your_key
SPACES_SECRET=your_secret
SPACES_REGION=nyc3
SPACES_BUCKET=betascrubber-frames
SPACES_ENDPOINT=https://nyc3.digitaloceanspaces.com

# Start local server
php -S localhost:8000 -t public
```