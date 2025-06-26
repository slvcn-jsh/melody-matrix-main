<?php
session_start();
require 'db.php';

// Fetch tracks from the music table
$stmt = $pdo->query('SELECT * FROM music ORDER BY id ASC');
$playlist = $stmt->fetchAll();

// Debug: Output playlist array to check if tracks are fetched
// Remove this after debugging
?><pre><?php print_r($playlist); ?></pre><?php

$playlistName = basename(__DIR__);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>MelodyMatrix</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
<button id="themeToggle" style="position:fixed;top:18px;right:24px;z-index:2000;background:var(--bg-card);color:var(--accent);border:none;border-radius:6px;padding:8px 18px;font-size:1rem;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,0.08);">🌙 Light Mode</button>
<div class="hamburger" id="hamburgerMenu">
  <span></span>
  <span></span>
  <span></span>
</div>
<div class="container">
  <!-- Sidebar -->
  <div class="sidebar" id="sidebar">
    <div class="categories">
      <div class="category active" id="myMusicTab">My Music</div>
      <div class="category" id="playlistTab">Playlist</div>
    </div>
    <div class="main-panel">
      <h2><?= htmlspecialchars($playlistName) ?></h2>
      <p><?= count($playlist) ?> tracks</p>
    </div>
    <!-- My Music List -->
    <ul class="track-list" id="myMusicList">
      <!-- Track items will be rendered by JS for pagination/filtering -->
    </ul>
    <!-- Playlist List (Initially hidden) -->
    <ul class="track-list" id="playlistList" style="display:none;">
      <!-- Track items will be rendered by JS for pagination/filtering -->
    </ul>
    <div id="playlistDetails" class="playlist-details" style="display:none;"></div>
    <div class="playlist-thumbnails" id="playlistThumbnails" style="margin-bottom:18px;display:none;"></div>
  </div>

  <!-- Player Section -->
  <div class="player">
    <div class="artist-info">
      <div class="cover-art-container">
        <img id="coverImage" class="rotating" src="<?= htmlspecialchars($playlist[0]['cover_image'] ?? 'assets/images/default.jpg') ?>" alt="Cover">
        <div class="vinyl-shadow"></div>
      </div>
      <div class="track-meta">
        <h2 id="artistName"><?= htmlspecialchars($playlist[0]['artist'] ?? '') ?></h2>
        <p id="trackTitle"><?= htmlspecialchars($playlist[0]['title'] ?? '') ?></p>
        <?php if (!empty($playlist[0]['album'])): ?>
          <span id="albumName" class="album-name"><?= htmlspecialchars($playlist[0]['album']) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <audio id="audioPlayer">
      <source src="<?= htmlspecialchars($playlist[0]['file_path'] ?? '') ?>" type="audio/mpeg">
      Your browser does not support the audio element.
    </audio>
    <div class="controls">
      <button id="shuffleBtn" title="Shuffle" aria-label="Shuffle">🔀</button>
      <button id="prevBtn" title="Previous" aria-label="Previous">⏮</button>
      <button id="playPauseBtn" title="Play/Pause" aria-label="Play/Pause">▶️</button>
      <button id="nextBtn" title="Next" aria-label="Next">⏭</button>
      <button id="repeatBtn" title="Repeat" aria-label="Repeat">🔁</button>
    </div>
    <div class="progress-container">
      <span id="currentTime">0:00</span>
      <input type="range" id="progressBar" min="0" max="100" value="0" step="0.1">
      <span id="remainingTime">-0:00</span>
    </div>
    <div class="volume-container">
      <span id="volumeIcon">🔊</span>
      <input type="range" id="volumeBar" min="0" max="1" step="0.01" value="1">
    </div>
    <div class="lyrics">
      <div id="lyricsContainer">
        <p id="lyricsLine">♫ Lyrics will go here ♫</p>
      </div>
    </div>
  </div>

  <!-- Right Panel -->
  <div class="right-panel" id="rightPanel">
    <div class="like-repost-group">
      <h3 id="likesCount">0 Likes</h3>
      <h3 id="repostsCount">0 Reposts</h3>
    </div>
    <h3>Concerts</h3>
    <ul id="concertsList">
      <!-- Concerts will be loaded here -->
    </ul>
    <h3>Comments</h3>
    <div class="comments" id="commentsSection">
      <!-- Comments will be loaded here -->
    </div>
    <?php if (isset($_SESSION['user_id'])): ?>
    <form id="commentForm">
      <input type="text" id="commentInput" placeholder="Add a comment..." required>
      <button type="submit">Post</button>
    </form>
    <?php else: ?>
    <p><a href="login.php">Login</a> to comment.</p>
    <?php endif; ?>
  </div>
</div>

<script>
  const playlist = <?= json_encode($playlist) ?>;
  let currentTrack = 0;
  const audio = document.getElementById('audioPlayer');
  const playPauseBtn = document.getElementById('playPauseBtn');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const trackTitle = document.getElementById('trackTitle');
  const artistName = document.getElementById('artistName');
  const coverImage = document.getElementById('coverImage');

  // Tabs and lists
  const myMusicTab = document.getElementById('myMusicTab');
  const playlistTab = document.getElementById('playlistTab');
  const myMusicList = document.getElementById('myMusicList');
  const playlistList = document.getElementById('playlistList');

  // --- Controls ---
  const progressBar = document.getElementById('progressBar');
  const currentTimeEl = document.getElementById('currentTime');
  const remainingTimeEl = document.getElementById('remainingTime');
  const volumeBar = document.getElementById('volumeBar');
  const volumeIcon = document.getElementById('volumeIcon');
  const shuffleBtn = document.getElementById('shuffleBtn');
  const repeatBtn = document.getElementById('repeatBtn');

  let isPlaying = false;
  let isShuffle = false;
  let repeatMode = 0; // 0: none, 1: repeat all, 2: repeat one

  // --- Progress Bar: show mm:ss for both current and remaining time ---
  function formatTime(sec) {
    sec = Math.max(0, Math.floor(sec));
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
  }
  audio.addEventListener('loadedmetadata', () => {
    progressBar.max = audio.duration;
    progressBar.value = 0;
    currentTimeEl.textContent = formatTime(audio.currentTime);
    remainingTimeEl.textContent = '-' + formatTime(audio.duration - audio.currentTime);
  });
  audio.addEventListener('timeupdate', () => {
    if (!isNaN(audio.duration)) {
      progressBar.max = audio.duration;
      progressBar.value = audio.currentTime;
      currentTimeEl.textContent = formatTime(audio.currentTime);
      remainingTimeEl.textContent = '-' + formatTime(audio.duration - audio.currentTime);
    }
  });
  progressBar.addEventListener('input', () => {
    audio.currentTime = progressBar.value;
  });
  audio.addEventListener('ended', () => {
    setCoverRotation(false);
    if (repeatMode === 2) {
      audio.currentTime = 0;
      audio.play();
    } else if (isShuffle) {
      playRandomTrack();
    } else if (repeatMode === 1) {
      nextBtn.click();
    } else {
      nextBtn.click();
    }
  });

  // --- Volume ---
  volumeBar.addEventListener('input', () => {
    audio.volume = volumeBar.value;
    if (audio.volume === 0) {
      volumeIcon.textContent = '🔇';
    } else if (audio.volume < 0.5) {
      volumeIcon.textContent = '🔉';
    } else {
      volumeIcon.textContent = '🔊';
    }
  });
  audio.volume = 1;

  // --- Shuffle ---
  shuffleBtn.addEventListener('click', () => {
    isShuffle = !isShuffle;
    shuffleBtn.style.color = isShuffle ? '#e040fb' : '';
    shuffleBtn.style.transform = isShuffle ? 'scale(1.2)' : '';
    setTimeout(() => shuffleBtn.style.transform = '', 200);
  });
  function playRandomTrack() {
    let next;
    do {
      next = Math.floor(Math.random() * playlist.length);
    } while (next === currentTrack && playlist.length > 1);
    currentTrack = next;
    loadTrack(currentTrack);
  }

  // --- Repeat ---
  repeatBtn.addEventListener('click', () => {
    repeatMode = (repeatMode + 1) % 3;
    if (repeatMode === 0) {
      repeatBtn.textContent = '🔁';
      repeatBtn.style.color = '';
    } else if (repeatMode === 1) {
      repeatBtn.textContent = '🔂';
      repeatBtn.style.color = '#e040fb';
    } else {
      repeatBtn.textContent = '🔂1';
      repeatBtn.style.color = '#ff4081';
    }
    repeatBtn.style.transform = 'scale(1.2)';
    setTimeout(() => repeatBtn.style.transform = '', 200);
  });

  // --- Album cover: use webp if available, fallback to jpg ---
  function getCoverImageSrc(track) {
    if (!track.cover_image) return 'assets/images/default.jpg';
    // Try to use .webp in images_webp folder if cover_image is jpg
    const jpgPath = track.cover_image;
    if (jpgPath.endsWith('.jpg')) {
      const webpPath = jpgPath.replace('/images/', '/images_webp/').replace('.jpg', '.webp');
      return webpPath;
    }
    return track.cover_image;
  }

  // --- Load Track ---
  function loadTrack(index) {
    const track = playlist[index];
    audio.src = track.file_path;
    trackTitle.textContent = track.title;
    artistName.textContent = track.artist;
    coverImage.setAttribute('data-src', getCoverImageSrc(track));
    coverImage.src = '';
    // Album name
    const albumName = document.getElementById('albumName');
    if (albumName) {
      albumName.textContent = track.album || '';
      albumName.style.display = track.album ? '' : 'none';
    }
    // Show lyrics if available
    const lyricsLine = document.getElementById('lyricsLine');
    const lyricsContainer = document.getElementById('lyricsContainer');
    if (track.lyrics && track.lyrics.trim() !== '') {
      displayLyrics(track.lyrics);
    } else {
      lyricsContainer.innerHTML = '<p id="lyricsLine">♫ Lyrics not available ♫</p>';
      lyricsData = null;
    }
    updateActiveTrack(index);
    audio.play();
    playPauseBtn.textContent = '⏸️';
    isPlaying = true;
    // Start album art rotation
    setCoverRotation(true);
    updateRightPanel(index in playlist ? playlist[index].id : 0);
    audio.currentTime = 0;
    progressBar.value = 0;
    if (!isNaN(audio.duration)) {
      progressBar.max = audio.duration;
      remainingTimeEl.textContent = '-' + formatTime(audio.duration);
    } else {
      remainingTimeEl.textContent = '-0:00';
    }
  }

  function animateCoverArt() {
    coverImage.classList.remove('song-change-animate');
    void coverImage.offsetWidth; // force reflow
    coverImage.classList.add('song-change-animate');
    setTimeout(() => coverImage.classList.remove('song-change-animate'), 700);
  }

  // Enhance loadTrack to animate cover art on song change
  const _originalLoadTrack = loadTrack;
  loadTrack = function(index) {
    _originalLoadTrack(index);
    animateCoverArt();
  }

  // --- Lyric Sync ---
  let lyricsData = null;
  let lyricsTimer = null;

  function parseLyrics(lyricsRaw) {
    // Support LRC format: [mm:ss.xx] line
    const lines = lyricsRaw.split(/\r?\n/);
    const result = [];
    for (const line of lines) {
      const match = line.match(/^\[(\d{1,2}):(\d{2})(?:\.(\d{1,2}))?\](.*)$/);
      if (match) {
        const min = parseInt(match[1], 10);
        const sec = parseInt(match[2], 10);
        const ms = match[3] ? parseInt(match[3].padEnd(2, '0'), 10) : 0;
        const time = min * 60 + sec + ms / 100;
        result.push({ time, text: match[4].trim() });
      } else if (line.trim() !== '') {
        // No timestamp, treat as static line
        result.push({ time: null, text: line.trim() });
      }
    }
    return result;
  }

  function displayLyrics(lyricsRaw) {
    const container = document.getElementById('lyricsContainer');
    container.innerHTML = '';
    if (!lyricsRaw || lyricsRaw.trim() === '') {
      container.innerHTML = '<p id="lyricsLine">♫ Lyrics not available ♫</p>';
      lyricsData = null;
      return;
    }
    lyricsData = parseLyrics(lyricsRaw);
    for (const l of lyricsData) {
      const p = document.createElement('p');
      p.textContent = l.text;
      p.className = 'lyric-line';
      container.appendChild(p);
    }
  }

  function syncLyrics() {
    if (!lyricsData || lyricsData.length === 0) return;
    const current = audio.currentTime;
    let activeIdx = -1;
    for (let i = 0; i < lyricsData.length; i++) {
      if (lyricsData[i].time !== null && lyricsData[i].time <= current) {
        activeIdx = i;
      }
    }
    const lines = document.querySelectorAll('#lyricsContainer .lyric-line');
    lines.forEach((el, i) => {
      if (i === activeIdx) {
        el.classList.add('active');
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else {
        el.classList.remove('active');
      }
    });
  }

  audio.addEventListener('timeupdate', syncLyrics);

  function updateActiveTrack(index) {
    // Remove active class from all tracks in both lists
    [...myMusicList.children].forEach(li => li.classList.remove('active'));
    [...playlistList.children].forEach(li => li.classList.remove('active'));

    // Add active class to current track in the visible list
    if (myMusicList.style.display !== 'none') {
      myMusicList.children[index].classList.add('active');
    } else {
      playlistList.children[index].classList.add('active');
    }
  }

  // Helper to fetch and update right panel info
  function updateRightPanel(trackId) {
    // Likes
    fetch('track_info.php?action=likes&id=' + trackId)
      .then(r => r.json()).then(data => {
        document.getElementById('likesCount').innerHTML = `<span class='icon-heart' style='color:#e040fb;font-size:1.2em;'>&#10084;</span> <span>${data.count || 0}</span> Likes`;
      });
    // Reposts
    fetch('track_info.php?action=reposts&id=' + trackId)
      .then(r => r.json()).then(data => {
        document.getElementById('repostsCount').innerHTML = `<span class='icon-repost' style='color:#00bcd4;font-size:1.2em;'>&#128257;</span> <span>${data.count || 0}</span> Reposts`;
      });
    // Concerts
    fetch('track_info.php?action=concerts&artist=' + encodeURIComponent(playlist[trackId].artist))
      .then(r => r.json()).then(data => {
        const ul = document.getElementById('concertsList');
        ul.innerHTML = '';
        (data.concerts || []).forEach(c => {
          const li = document.createElement('li');
          li.textContent = c.location + ' - ' + c.date;
          ul.appendChild(li);
        });
      });
    // Comments (with paging)
    loadComments(trackId, 0, true);
    // Start polling for likes/reposts
    startLikesRepostsPolling(trackId);
  }

  // --- Real-time Likes & Reposts Polling ---
  let likesRepostsInterval = null;
  function startLikesRepostsPolling(trackId) {
    if (likesRepostsInterval) clearInterval(likesRepostsInterval);
    likesRepostsInterval = setInterval(() => {
      fetch('track_info.php?action=likes&id=' + trackId)
        .then(r => r.json()).then(data => {
          document.getElementById('likesCount').innerHTML = `<span class='icon-heart' style='color:#e040fb;font-size:1.2em;'>&#10084;</span> <span>${data.count || 0}</span> Likes`;
        });
      fetch('track_info.php?action=reposts&id=' + trackId)
        .then(r => r.json()).then(data => {
          document.getElementById('repostsCount').innerHTML = `<span class='icon-repost' style='color:#00bcd4;font-size:1.2em;'>&#128257;</span> <span>${data.count || 0}</span> Reposts`;
        });
    }, 3000); // Poll every 3 seconds
  }

  // --- Social Media Share Buttons ---
  function renderShareButtons(track) {
    const url = encodeURIComponent(window.location.href + '?track=' + track.id);
    const text = encodeURIComponent(`Check out this track: ${track.title} by ${track.artist} on MelodyMatrix!`);
    return `
      <div class="share-buttons" style="margin:10px 0;display:flex;gap:10px;">
        <a href="https://www.facebook.com/sharer/sharer.php?u=${url}" target="_blank" title="Share on Facebook" class="action-btn" style="color:#4267B2;font-size:1.3em;">&#xf09a;</a>
        <a href="https://twitter.com/intent/tweet?url=${url}&text=${text}" target="_blank" title="Share on Twitter" class="action-btn" style="color:#1da1f2;font-size:1.3em;">&#xf099;</a>
        <a href="https://wa.me/?text=${text}%20${url}" target="_blank" title="Share on WhatsApp" class="action-btn" style="color:#25d366;font-size:1.3em;">&#xf232;</a>
      </div>
    `;
  }

  // Add share buttons to right panel after likes/reposts
  function updateRightPanel(trackId) {
    // Likes
    fetch('track_info.php?action=likes&id=' + trackId)
      .then(r => r.json()).then(data => {
        document.getElementById('likesCount').innerHTML = `<span class='icon-heart' style='color:#e040fb;font-size:1.2em;'>&#10084;</span> <span>${data.count || 0}</span> Likes`;
      });
    // Reposts
    fetch('track_info.php?action=reposts&id=' + trackId)
      .then(r => r.json()).then(data => {
        document.getElementById('repostsCount').innerHTML = `<span class='icon-repost' style='color:#00bcd4;font-size:1.2em;'>&#128257;</span> <span>${data.count || 0}</span> Reposts`;
      });
    // Concerts
    fetch('track_info.php?action=concerts&artist=' + encodeURIComponent(playlist[trackId].artist))
      .then(r => r.json()).then(data => {
        const ul = document.getElementById('concertsList');
        ul.innerHTML = '';
        (data.concerts || []).forEach(c => {
          const li = document.createElement('li');
          li.textContent = c.location + ' - ' + c.date;
          ul.appendChild(li);
        });
      });
    // Comments (with paging)
    loadComments(trackId, 0, true);
    // Insert share buttons below repostsCount
    const rightPanel = document.getElementById('rightPanel');
    const shareDiv = document.getElementById('shareButtonsDiv') || document.createElement('div');
    shareDiv.id = 'shareButtonsDiv';
    shareDiv.innerHTML = renderShareButtons(playlist.find(t => t.id == trackId));
    const repostsCount = document.getElementById('repostsCount');
    if (repostsCount && shareDiv.parentNode !== rightPanel) {
      repostsCount.insertAdjacentElement('afterend', shareDiv);
    }
    // Start polling for likes/reposts
    startLikesRepostsPolling(trackId);
  }

  // --- Infinite Scroll/Load More for Comments ---
  let commentsPage = 0;
  const COMMENTS_PER_PAGE = 8;
  function loadComments(trackId, page = 0, reset = false) {
    fetch(`track_info.php?action=comments&id=${trackId}&page=${page}&per_page=${COMMENTS_PER_PAGE}`)
      .then(r => r.json()).then(data => {
        const section = document.getElementById('commentsSection');
        if (reset) {
          section.innerHTML = '';
          commentsPage = 0;
        }
        // Sort comments by likes
        const sorted = sortCommentsByLikes(data.comments || []);
        (sorted).forEach(c => {
          section.insertAdjacentHTML('beforeend', renderCommentItem(c));
        });
        // Add Load More button if more comments
        let loadMoreBtn = document.getElementById('loadMoreCommentsBtn');
        if (data.has_more) {
          if (!loadMoreBtn) {
            loadMoreBtn = document.createElement('button');
            loadMoreBtn.id = 'loadMoreCommentsBtn';
            loadMoreBtn.textContent = 'Load more comments';
            loadMoreBtn.style = 'margin:10px auto;display:block;background:var(--accent);color:#fff;border:none;border-radius:6px;padding:7px 18px;cursor:pointer;';
            loadMoreBtn.onclick = function() {
              commentsPage++;
              loadComments(trackId, commentsPage, false);
            };
            section.parentElement.appendChild(loadMoreBtn);
          }
        } else if (loadMoreBtn) {
          loadMoreBtn.remove();
        }
      });
  }

  // --- Like Comments Sorting ---
  function sortCommentsByLikes(comments) {
    return comments.slice().sort((a, b) => (b.likes || 0) - (a.likes || 0));
  }
  // Patch loadComments to sort by likes
  const originalLoadComments = loadComments;
  loadComments = function(trackId, page = 0, reset = false) {
    fetch(`track_info.php?action=comments&id=${trackId}&page=${page}&per_page=${COMMENTS_PER_PAGE}`)
      .then(r => r.json()).then(data => {
        const section = document.getElementById('commentsSection');
        if (reset) {
          section.innerHTML = '';
          commentsPage = 0;
        }
        // Sort comments by likes
        const sorted = sortCommentsByLikes(data.comments || []);
        (sorted).forEach(c => {
          section.insertAdjacentHTML('beforeend', renderCommentItem(c));
        });
        // Add Load More button if more comments
        let loadMoreBtn = document.getElementById('loadMoreCommentsBtn');
        if (data.has_more) {
          if (!loadMoreBtn) {
            loadMoreBtn = document.createElement('button');
            loadMoreBtn.id = 'loadMoreCommentsBtn';
            loadMoreBtn.textContent = 'Load more comments';
            loadMoreBtn.style = 'margin:10px auto;display:block;background:var(--accent);color:#fff;border:none;border-radius:6px;padding:7px 18px;cursor:pointer;';
            loadMoreBtn.onclick = function() {
              commentsPage++;
              loadComments(trackId, commentsPage, false);
            };
            section.parentElement.appendChild(loadMoreBtn);
          }
        } else if (loadMoreBtn) {
          loadMoreBtn.remove();
        }
      });
  };

  // --- Comment Action Handlers (Like, Reply, Report) ---
  document.getElementById('commentsSection').addEventListener('click', function(e) {
    const likeBtn = e.target.closest('.like-comment');
    const replyBtn = e.target.closest('.reply-comment');
    const reportBtn = e.target.closest('.report-comment');
    if (likeBtn) {
      const cid = likeBtn.getAttribute('data-cid');
      fetch('track_info.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'like_comment', comment_id: cid })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) updateRightPanel(playlist[currentTrack].id);
      });
    } else if (replyBtn) {
      const cid = replyBtn.getAttribute('data-cid');
      const reply = prompt('Reply to this comment:');
      if (reply && reply.trim().length > 0 && reply.length <= 280) {
        fetch('track_info.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'reply_comment', comment_id: cid, reply })
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) updateRightPanel(playlist[currentTrack].id);
        });
      }
    } else if (reportBtn) {
      const cid = reportBtn.getAttribute('data-cid');
      if (confirm('Report this comment for moderation?')) {
        fetch('track_info.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'report_comment', comment_id: cid })
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) alert('Comment reported for moderation.');
        });
      }
    }
  });

  // Microinteraction for posting a comment
  const commentForm = document.getElementById('commentForm');
  if (commentForm) {
    const commentInput = document.getElementById('commentInput');
    commentInput.maxLength = 280; // Character limit
    commentInput.addEventListener('input', function() {
      if (this.value.length > 280) this.value = this.value.slice(0, 280);
    });
    commentForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const comment = commentInput.value.trim();
      if (comment.length === 0) return;
      fetch('track_info.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add_comment', id: playlist[currentTrack].id, comment })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          commentInput.value = '';
          updateRightPanel(playlist[currentTrack].id);
          // Animate comment section
          const section = document.getElementById('commentsSection');
          section.style.boxShadow = '0 0 12px #ff4081';
          setTimeout(() => section.style.boxShadow = '', 400);
        }
      });
  }

  // --- Enhanced Comments: Like, Reply, Report (with replies display) ---
  function renderCommentActions(comment, trackId) {
    return `
      <span class="comment-action like-comment" data-cid="${comment.id}" title="Like">&#10084; <span>${comment.likes || 0}</span></span>
      <span class="comment-action reply-comment" data-cid="${comment.id}" title="Reply">&#128172;</span>
      <span class="comment-action report-comment" data-cid="${comment.id}" title="Report">&#9888;</span>
    `;
  }

  function renderReplies(replies) {
    if (!replies || !replies.length) return '';
    return `<div class='comment-replies'>` + replies.map(r =>
      `<div class='comment-reply'>
        <img class='comment-avatar' src='${r.avatar || "assets/images/default-avatar.png"}' alt='avatar'>
        <strong>${r.username}:</strong> <span>${r.reply}</span>
        <span class="comment-action reply-comment" data-cid="${r.id}" title="Reply">&#128172;</span>
      </div>`
    ).join('') + `</div>`;
  }

  // Patch main comment rendering to show avatar and threaded replies
  function renderCommentItem(c) {
    return `<div class='comment-item'>
      <img class='comment-avatar' src='${c.avatar || "assets/images/default-avatar.png"}' alt='avatar'>
      <strong>${c.username}:</strong> <span>${c.comment}</span>
      <div class='comment-actions'>${renderCommentActions(c, c.track_id)}</div>
      ${renderReplies(c.replies)}
    </div>`;
  }

  // Patch loadComments to use renderCommentItem
  loadComments = function(trackId, page = 0, reset = false) {
    fetch(`track_info.php?action=comments&id=${trackId}&page=${page}&per_page=${COMMENTS_PER_PAGE}`)
      .then(r => r.json()).then(data => {
        const section = document.getElementById('commentsSection');
        if (reset) {
          section.innerHTML = '';
          commentsPage = 0;
        }
        // Sort comments by likes
        const sorted = sortCommentsByLikes(data.comments || []);
        (sorted).forEach(c => {
          section.insertAdjacentHTML('beforeend', renderCommentItem(c));
        });
        // Add Load More button if more comments
        let loadMoreBtn = document.getElementById('loadMoreCommentsBtn');
        if (data.has_more) {
          if (!loadMoreBtn) {
            loadMoreBtn = document.createElement('button');
            loadMoreBtn.id = 'loadMoreCommentsBtn';
            loadMoreBtn.textContent = 'Load more comments';
            loadMoreBtn.style = 'margin:10px auto;display:block;background:var(--accent);color:#fff;border:none;border-radius:6px;padding:7px 18px;cursor:pointer;';
            loadMoreBtn.onclick = function() {
              commentsPage++;
              loadComments(trackId, commentsPage, false);
            };
            section.parentElement.appendChild(loadMoreBtn);
          }
        } else if (loadMoreBtn) {
          loadMoreBtn.remove();
        }
      });
  };

  // Hamburger menu for mobile
  const hamburger = document.getElementById('hamburgerMenu');
  const sidebar = document.getElementById('sidebar');
  hamburger.addEventListener('click', () => {
    sidebar.classList.toggle('open');
  });
  // Close sidebar when clicking outside (mobile)
  document.addEventListener('click', function(e) {
    if (window.innerWidth <= 900 && sidebar.classList.contains('open')) {
      if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    }
  });

  // Responsive: Show/hide right panel details on mobile
  function handleRightPanelMobile() {
    const rightPanel = document.getElementById('rightPanel');
    const moreToggle = document.getElementById('moreToggle');
    if (window.innerWidth <= 900) {
      moreToggle.style.display = 'block';
      rightPanel.classList.remove('expanded');
      moreToggle.onclick = function() {
        rightPanel.classList.toggle('expanded');
        moreToggle.textContent = rightPanel.classList.contains('expanded') ? 'Less' : 'More';
      };
    } else {
      moreToggle.style.display = 'none';
      rightPanel.classList.remove('expanded');
    }
  }
  window.addEventListener('resize', handleRightPanelMobile);
  window.addEventListener('DOMContentLoaded', handleRightPanelMobile);

  // Theme toggle
  const themeToggle = document.getElementById('themeToggle');
  function setTheme(mode) {
    if (mode === 'light') {
      document.body.classList.add('light-mode');
      themeToggle.textContent = '🌑 Dark Mode';
      localStorage.setItem('theme', 'light');
    } else {
      document.body.classList.remove('light-mode');
      themeToggle.textContent = '🌙 Light Mode';
      localStorage.setItem('theme', 'dark');
    }
  }
  themeToggle.addEventListener('click', function() {
    setTheme(document.body.classList.contains('light-mode') ? 'dark' : 'light');
  });
  // On load, set theme from localStorage
  window.addEventListener('DOMContentLoaded', function() {
    setTheme(localStorage.getItem('theme') === 'light' ? 'light' : 'dark');
  });

  // --- Render Track List ---
  function renderTrackList(listId, tracks) {
    const list = document.getElementById(listId);
    if (!list) return;
    list.innerHTML = '';
    if (!tracks || tracks.length === 0) {
      list.innerHTML = '<li style="text-align:center;color:var(--text-muted);">No tracks found.</li>';
      return;
    }
    tracks.forEach((track, i) => {
      const li = document.createElement('li');
      li.className = 'track-item';
      if (i === currentTrack) li.classList.add('active');
      li.innerHTML = `
        <span class="track-title">${track.title || 'Untitled'}</span>
        <span class="track-artist" style="margin-left:10px;color:var(--accent);">${track.artist || ''}</span>
        <div class="track-actions">
          <button class="action-btn icon-heart" data-track-id="${track.id}" title="Like">&#10084;</button>
          <button class="action-btn icon-repost" data-track-id="${track.id}" title="Repost">&#128257;</button>
          <button class="action-btn icon-play" data-track-id="${track.id}" title="Play">▶️</button>
        </div>
      `;
      li.addEventListener('click', function(e) {
        if (e.target.closest('.action-btn')) return;
        currentTrack = i;
        loadTrack(currentTrack);
        playCurrentTrack();
      });
      li.querySelector('.icon-play').addEventListener('click', function(e) {
        e.stopPropagation();
        currentTrack = i;
        loadTrack(currentTrack);
        playCurrentTrack();
      });
      list.appendChild(li);
    });
  }

  // --- Initial Render on Page Load ---
  window.addEventListener('DOMContentLoaded', function() {
    renderTrackList('myMusicList', playlist);
  });

  // --- Tab Switching Logic ---
  myMusicTab.addEventListener('click', function() {
    myMusicTab.classList.add('active');
    playlistTab.classList.remove('active');
    myMusicList.style.display = '';
    playlistList.style.display = 'none';
    renderTrackList('myMusicList', playlist);
  });
  playlistTab.addEventListener('click', function() {
    playlistTab.classList.add('active');
    myMusicTab.classList.remove('active');
    myMusicList.style.display = 'none';
    playlistList.style.display = '';
    renderTrackList('playlistList', playlist);
  });
</script>

<style>
.cover-art-container {
  position: relative;
  width: 180px;
  height: 180px;
  margin: 0 auto 18px auto;
  display: flex;
  align-items: center;
  justify-content: center;
}
#coverImage {
  width: 170px;
  height: 170px;
  border-radius: 50%;
  box-shadow: 0 8px 32px rgba(0,0,0,0.18), 0 2px 8px rgba(0,0,0,0.08);
  object-fit: cover;
  background: var(--bg-card);
  transition: box-shadow 0.3s, filter 0.3s;
}
#coverImage.rotating {
  animation: spin 4s linear infinite;
}
@keyframes spin {
  100% { transform: rotate(360deg); }
}
.vinyl-shadow {
  position: absolute;
  width: 180px;
  height: 180px;
  border-radius: 50%;
  background: radial-gradient(circle at 60% 40%, rgba(0,0,0,0.12) 0%, rgba(0,0,0,0.04) 80%, transparent 100%);
  z-index: 0;
  pointer-events: none;
}
.artist-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 12px;
}
.track-meta {
  text-align: center;
  margin-top: 10px;
}
#artistName {
  font-family: 'Montserrat', sans-serif;
  font-size: 1.3rem;
  font-weight: 700;
  margin: 0 0 2px 0;
}
#trackTitle {
  font-family: 'Open Sans', sans-serif;
  font-size: 1.1rem;
  font-weight: 500;
  margin: 0 0 2px 0;
}
.album-name {
  display: block;
  font-size: 0.95rem;
  color: var(--accent);
  margin-top: 2px;
  font-style: italic;
}
.controls {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 18px;
  margin: 18px 0 8px 0;
}
.controls button {
  background: var(--bg-card);
  border: none;
  border-radius: 50%;
  width: 48px;
  height: 48px;
  font-size: 1.5rem;
  color: var(--accent);
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  cursor: pointer;
  transition: background 0.2s, transform 0.2s, color 0.2s;
}
.controls button:active {
  background: var(--accent);
  color: #fff;
  transform: scale(1.1);
}
.progress-container {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  margin: 0 0 8px 0;
}
#progressBar {
  width: 220px;
  accent-color: var(--accent);
  height: 4px;
  border-radius: 2px;
  background: var(--bg-card);
}
#currentTime, #remainingTime {
  font-size: 0.95rem;
  color: var(--accent);
  min-width: 40px;
  text-align: center;
}
.volume-container {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-bottom: 10px;
}
#volumeBar {
  width: 90px;
  accent-color: var(--accent);
}
#volumeIcon {
  font-size: 1.2rem;
  color: var(--accent);
}
.lyrics {
  max-height: 120px;
  overflow: hidden;
  margin: 0 auto;
  padding: 0 8px;
  position: relative;
}
#lyricsContainer {
  max-height: 120px;
  overflow-y: auto;
  scroll-behavior: smooth;
  text-align: center;
}
.lyric-line {
  font-size: 1.05rem;
  color: var(--text);
 
  opacity: 1;
  font-size: 1.18rem;
  font-weight: 700;
  background: linear-gradient(90deg, var(--accent) 0%, transparent 100%);
  border-radius: 6px;
}
#lyricsContainer::-webkit-scrollbar {
  width: 0;
  background: transparent;
}
.track-search {
  width: 100%;
  padding: 7px 12px;
  border-radius: 6px;
  border: 1px solid var(--bg-card);
  margin: 8px 0 10px 0;
  font-size: 1rem;
  background: var(--bg);
  color: var(--text);
  outline: none;
  box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.track-list {
  list-style: none;
  padding: 0;
  margin: 0;
  max-height: 340px;
  overflow-y: auto;
}
.track-list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  border-radius: 7px;
  margin-bottom: 4px;
  background: var(--bg-card);
  cursor: pointer;
  transition: background 0.18s, box-shadow 0.18s;
  position: relative;
}
.track-list li.active {
  background: linear-gradient(90deg, var(--accent) 0%, var(--bg-card) 100%);
  color: #fff;
  font-weight: 700;
}
.track-list li:hover {
  background: var(--accent);
  color: #fff;
  box-shadow: 0 2px 12px rgba(224,64,251,0.08);
}
.track-title {
  flex: 1 1 auto;
  font-size: 1.05rem;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.track-plays {
  margin-left: 12px;
  font-size: 0.95rem;
  color: var(--accent);
}
.track-hover-info {
  display: none;
  position: absolute;
  left: 12px;
  top: 100%;
  background: var(--bg-card);
  color: var(--text);
  border-radius: 6px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  padding: 7px 14px 10px 14px;
  font-size: 0.97rem;
  z-index: 10;
  min-width: 180px;
  margin-top: 2px;
  flex-direction: column;
  gap: 2px;
}
.track-list li:hover .track-hover-info {
  display: flex;
}
.track-actions {
  display: flex;
  gap: 8px;
  margin-top: 7px;
  justify-content: flex-end;
}
.action-btn {
  background: none;
  border: none;
  color: var(--accent);
  font-size: 1.15rem;
  cursor: pointer;
  border-radius: 50%;
  padding: 5px 7px;
  transition: background 0.18s, color 0.18s, transform 0.18s;
}
.action-btn:hover {
  background: var(--accent);
  color: #fff;
  transform: scale(1.18);
}
.icon-heart { color: #e040fb; }
.icon-repost { color: #00bcd4; }
.icon-comment { color: #ff9800; }
.icon-play { color: #4caf50; }
.icon-delete { color: #f44336; }
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin: 10px 0 0 0;
}
.page-btn {
  background: var(--bg-card);
  border: none;
  border-radius: 5px;
  padding: 5px 14px;
  font-size: 1rem;
  color: var(--accent);
  cursor: pointer;
  transition: background 0.18s, color 0.18s;
}
.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.playlist-thumbnails {
  display: flex;
  gap: 18px;
  flex-wrap: wrap;
  margin-bottom: 10px;
}
.playlist-thumb {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: var(--bg-card);
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  padding: 10px 16px;
  cursor: pointer;
  transition: box-shadow 0.18s, background 0.18s;
  min-width: 120px;
  max-width: 140px;
}
.playlist-thumb:hover {
  background: var(--accent);
  color: #fff;
  box-shadow: 0 4px 16px rgba(224,64,251,0.12);
}
.playlist-thumb img {
  width: 80px;
  height: 80px;
  border-radius: 8px;
  object-fit: cover;
  margin-bottom: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.playlist-thumb-title {
  font-weight: 700;
  font-size: 1.05rem;
  margin-bottom: 2px;
}
.playlist-thumb-count {
  font-size: 0.95rem;
  color: var(--accent);
}
.playlist-details {
  background: var(--bg-card);
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  padding: 18px 22px;
  margin: 10px 0;
  position: relative;
  z-index: 20;
}
.playlist-details h3 {
  margin-top: 0;
}
.playlist-details button {
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 6px 18px;
  font-size: 1rem;
  margin-top: 10px;
  cursor: pointer;
}
.track-list li.dragging {
  opacity: 0.5;
  background: #e0e0e0;
}
.track-list li.drag-over {
  outline: 2px dashed var(--accent);
  background: #f3e6fa;
}
.top-search-bar { width: 100%; background: var(--bg-card); box-shadow: 0 2px 8px rgba(0,0,0,0.04); padding: 18px 0 8px 0; position: sticky; top: 0; z-index: 2001; }
.search-bar-container { max-width: 700px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; }
.global-search-input { width: 100%; max-width: 420px; padding: 10px 14px; border-radius: 7px; border: 1px solid var(--accent); font-size: 1.1rem; margin-bottom: 7px; background: var(--bg); color: var(--text); outline: none; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
.autocomplete-suggestions { position: absolute; background: var(--bg-card); border-radius: 7px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); max-height: 220px; overflow-y: auto; width: 420px; left: 50%; transform: translateX(-50%); top: 54px; z-index: 2002; display: none; }
.suggestion-item { padding: 8px 12px; cursor: pointer; display: flex; gap: 10px; align-items: center; border-bottom: 1px solid #eee; }
.suggestion-item:last-child { border-bottom: none; }
.suggestion-item:hover { background: var(--accent); color: #fff; }
.sugg-title { font-weight: 700; }
.sugg-artist, .sugg-album, .sugg-genre { font-size: 0.97em; color: var(--accent); margin-left: 6px; }
.search-filters { display: flex; gap: 10px; margin-top: 7px; align-items: center; }
.search-filters select,
#clearFiltersBtn { background: var(--accent); color: #fff; border: none; border-radius: 5px; padding: 5px 14px; cursor: pointer; }
@media (max-width: 600px) { .global-search-input, .autocomplete-suggestions { width: 98vw; max-width: 98vw; } }

.rec-carousel {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 16px;
  margin-top: 10px;
}
.rec-card {
  background: var(--bg-card);
  border-radius: 8px;
  overflow: hidden;
  position: relative;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}
.rec-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.rec-cover {
  width: 100%;
  height: 100px;
  object-fit: cover;
}
.rec-meta {
  padding: 10px;
  text-align: center;
}
.rec-title {
  font-size: 1rem;
  font-weight: 500;
  margin: 0;
  color: var(--text);
}
.rec-artist {
  font-size: 0.9rem;
  color: var(--accent);
  margin: 4px 0 0 0;
}
.rec-album {
  font-size: 0.85rem;
  color: var(--text-muted);
  margin: 2px 0 0 0;
}
.rec-play {
  position: absolute;
  bottom: 8px;
  right: 8px;
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 50%;
  width: 36px;
  height: 36px;
  font-size: 1.2rem;
  cursor: pointer;
  transition: background 0.2s, transform 0.2s;
}
.rec-play:hover {
  background: #e040fb;
  transform: scale(1.1);
}
</style>

</body>
</html>
