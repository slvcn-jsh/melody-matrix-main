-- Melody Matrix: Batch insert for Song Writer playlist tracks
-- Please update the file_path and cover_image to match the actual files in your song writer playlist/audio/ and song writer playlist/images/ folders
INSERT INTO music (title, artist, file_path, cover_image) VALUES
-- Example entries (replace with your actual song writer playlist tracks)
('Songwriter Track 1', 'Artist 1', 'assets/song writer playlist/audio/songwriter_track1.mp3', 'assets/song writer playlist/images/songwriter_track1.jpg'),
('Songwriter Track 2', 'Artist 2', 'assets/song writer playlist/audio/songwriter_track2.mp3', 'assets/song writer playlist/images/songwriter_track2.jpg');
-- Add more tracks as needed following the pattern above
