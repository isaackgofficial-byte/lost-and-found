<?php 
session_start();
include('db_connect.php');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$userId = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lost & Found Dashboard</title>
  <link rel="stylesheet" href="ui.css">
  <style>
    /* Notification Bell */
    .notification-bell { position: relative; font-size: 1.5rem; cursor: pointer; color: var(--text); }
    .notification-bell .badge { position: absolute; top: -5px; right: -8px; background: var(--warning); color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.7rem; font-weight: bold; }

    /* Popup Overlay (Modal) */
    .popup-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(17,17,17,0.8); backdrop-filter: blur(5px); align-items: center; justify-content: center; z-index: 2000; }
    .popup-overlay.active { display: flex; }

    .popup-content { background: var(--card-bg); border-radius: var(--radius); max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow); position: relative; }
    .popup-content img { width: 100%; object-fit: contain; border-radius: var(--radius); }

    .close-popup { position: absolute; top: 10px; right: 12px; background: var(--warning); border: none; color: white; font-size: 20px; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; }
    .close-popup:hover { background: #f50057; }

    /* Notifications Dropdown */
    #notificationList { position: absolute; right: 0; top: 40px; background: var(--card-bg); border-radius: var(--radius); box-shadow: 0 4px 12px rgba(0,0,0,0.15); width: 500px; max-height: 300px; overflow-y: auto; display: none; z-index: 3000; font-size: 0.9rem; }
    #notificationList.active { display: block; }

    .notification-item { display: flex; flex-direction: column; align-items: flex-start; padding: 10px 15px; border-bottom: 1px solid rgba(0,0,0,0.1); cursor: pointer; transition: background 0.2s; }
    .notification-item:last-child { border-bottom: none; }
    .notification-item:hover { background: rgba(0,0,0,0.05); }
    .notification-item small { color: var(--text-muted); font-size: 0.75rem; }

    /* Comments */
    .comments-list { max-height: 50vh; overflow-y: auto; }
    .comment-item { display:flex; gap:10px; margin-bottom:12px; }
    .comment-avatar { width:48px; height:48px; flex:0 0 48px; }
    .comment-avatar a { display:inline-block; width:100%; height:100%; }
    .comment-avatar img { width:100%; height:100%; object-fit:cover; border-radius:50%; cursor:pointer; }
    .comment-content { flex:1; }
    .comment-author { font-weight:600; cursor:pointer; }
    .comment-text { margin:4px 0; }
    .comment-date { font-size:0.8rem; color:var(--text-muted); }

    /* Item card tweaks */
    .item-card { border-radius:8px; box-shadow:var(--shadow); overflow:hidden; background:var(--card-bg); }
    .item-image { width:100%; height:160px; object-fit:cover; cursor:pointer; }
    .comments-toggle { cursor:pointer; }
  </style>
</head>
<body class="dark-mode">

  <!-- Navbar -->
  <nav class="navbar">
    <div class="logo">🔒 Lost & Found</div>
    <div class="nav-links">
      <a href="landingpage.php">HOME</a>
      <a href="ui.php" class="active">Dashboard</a>
      <a href="post.php">Post Item</a>
      <a href="items.php">My Items</a>
      <a href="profile.php">Profile</a>
    </div>
    <div class="nav-actions">
      <div class="notification-bell" id="notificationBell">
        🔔 <span class="badge" id="notificationCount">0</span>
        <div id="notificationList"></div>
      </div>
      <button class="theme-toggle" id="themeToggle">🌙</button>
      <button class="btn btn-outline" onclick="window.location.href='logout.php'">Logout</button>
    </div>
  </nav>

  <!-- Header -->
  <header class="header">
    <h1>Lost Something? We're Here to Help</h1>
    <p>Search through reported items or post what you've lost or found.</p>
  </header>

  <!-- Filters -->
  <section class="search-section">
    <input type="text" class="search-input" id="search" placeholder="Search by title, description, or category...">
    <select class="filter-select" id="filterCategory">
      <option value="All">All Categories</option>
      <option value="Electronics">Electronics</option>
      <option value="Clothing">Clothing</option>
      <option value="Documents">Documents</option>
      <option value="Accessories">Accessories</option>
      <option value="Other">Other</option>
    </select>
    <select class="filter-select" id="filterType">
      <option value="All">All Items</option>
      <option value="Lost">Lost</option>
      <option value="Found">Found</option>
    </select>
  </section>

  <!-- Items Section -->
  <section class="items-section">
    <h3 class="section-title">Recently Lost Items</h3>
    <div class="items-grid" id="lostItems"></div>

    <h2 class="section-title">Recently Found Items</h2>
    <div class="items-grid" id="foundItems"></div>
  </section>

  <!-- Image Modal -->
  <div id="imageModal" class="popup-overlay">
    <div class="popup-content">
      <button class="close-popup" onclick="closeModal('imageModal')">×</button>
      <img id="modalImg" src="" alt="Item Image">
    </div>
  </div>

  <!-- Comments Modal -->
  <div id="commentsModal" class="popup-overlay">
    <div class="popup-content comments-modal-content">
      <button class="close-popup" onclick="closeModal('commentsModal')">×</button>
      <h3 style="margin-bottom:10px;">Comments</h3>
      <div class="comments-list" id="commentsList"></div>
      <div class="add-comment">
        <input type="text" class="comment-input" id="newCommentText" placeholder="Add a comment...">
        <button class="comment-submit" id="commentSubmitBtn">Post</button>
      </div>
    </div>
  </div>

  <!-- Item Card Template -->
  <template id="itemTemplate">
    <div class="item-card">
      <div class="item-header">
        <img class="item-image" src="" alt="Item Image">
        <span class="item-type type-lost"></span>
      </div>
      <div class="item-body">
        <div class="item-title"></div>
        <div class="item-description"></div>
        <div class="item-meta"></div>
      </div>
      <div class="item-footer">
        <button class="comments-toggle">💬 View/Add Comments</button>
      </div>
    </div>
  </template>

  <!-- Footer -->
  <footer>
    &copy; <?= date('Y') ?> Lost & Found. All rights reserved.
  </footer>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const lostContainer = document.getElementById("lostItems");
  const foundContainer = document.getElementById("foundItems");
  const template = document.getElementById("itemTemplate");
  const searchInput = document.getElementById("search");
  const filterCategory = document.getElementById("filterCategory");
  const filterType = document.getElementById("filterType");
  const themeToggle = document.getElementById("themeToggle");
  const notificationBell = document.getElementById("notificationBell");
  const notificationCount = document.getElementById("notificationCount");
  const notificationList = document.getElementById("notificationList");

  // Dark/light toggle
  const currentTheme = localStorage.getItem("theme") || "dark";
  document.body.classList.toggle("dark-mode", currentTheme === "dark");
  themeToggle.textContent = currentTheme === "dark" ? "🌙" : "🌞";
  themeToggle.addEventListener("click", () => {
    const isDark = document.body.classList.toggle("dark-mode");
    localStorage.setItem("theme", isDark ? "dark" : "light");
    themeToggle.textContent = isDark ? "🌙" : "🌞";
  });

  let allItems = [];
  let notifications = [];
  let currentItemId = null; // item currently open in comments modal

  async function loadItems() {
    try {
      const res = await fetch('get_items.php');
      allItems = await res.json();
      displayItems();
    } catch (err) {
      lostContainer.innerHTML = '<p class="no-items">Failed to load items.</p>';
      foundContainer.innerHTML = '<p class="no-items">Failed to load items.</p>';
      console.error('Failed to load items', err);
    }
  }

  function displayItems() {
    const search = searchInput.value.toLowerCase();
    const category = filterCategory.value;
    const typeFilter = filterType.value;

    lostContainer.innerHTML = '';
    foundContainer.innerHTML = '';

    const filtered = allItems.filter(item => {
      const matchesSearch = (item.title || '').toLowerCase().includes(search) ||
                            (item.description || '').toLowerCase().includes(search) ||
                            (item.category || '').toLowerCase().includes(search);
      const matchesCategory = category === "All" || item.category === category;
      const matchesType = typeFilter === "All" || item.type === typeFilter;
      return matchesSearch && matchesCategory && matchesType;
    });

    if (!filtered.length) {
      if(typeFilter === 'Lost' || typeFilter === 'All')
        lostContainer.innerHTML = '<p class="no-items">No lost items found.</p>';
      if(typeFilter === 'Found' || typeFilter === 'All')
        foundContainer.innerHTML = '<p class="no-items">No found items found.</p>';
      return;
    }

    filtered.forEach(item => {
      const card = template.content.cloneNode(true);
      const imgEl = card.querySelector('.item-image');
      imgEl.src = item.image_path || 'uploads/default_image.png';
      imgEl.addEventListener("click", () => openModal(item.image_path || 'uploads/default_image.png'));

      card.querySelector('.item-title').textContent = item.title || 'Untitled';
      card.querySelector('.item-description').textContent = item.description || '';
      // Updated: Posted-by now links to public_profile.php
      card.querySelector('.item-meta').innerHTML = `
        <strong>Category:</strong> ${item.category || 'Other'}<br>
        <strong>Posted by:</strong> <a href="public_profile.php?user_id=${item.user_id}" class="posted-by-link">${item.full_name || 'User'}</a><br>
        <strong>Date:</strong> ${item.created_at ? new Date(item.created_at).toLocaleDateString() : 'Unknown'}
      `;

      const typeLabel = card.querySelector('.item-type');
      typeLabel.textContent = item.type || '';
      typeLabel.className = `item-type ${item.type === 'Lost' ? 'type-lost' : 'type-found'}`;

      const commentBtn = card.querySelector('.comments-toggle');
      commentBtn.addEventListener('click', () => openComments(item.id));

      if(item.type === 'Lost') lostContainer.appendChild(card);
      else foundContainer.appendChild(card);
    });
  }

  function openModal(src) {
    const modal = document.getElementById('imageModal');
    document.getElementById('modalImg').src = src;
    modal.classList.add('active');
  }
  window.closeModal = function(id) {
    document.getElementById(id).classList.remove('active');
  }

  const commentsList = document.getElementById('commentsList');
  const newCommentText = document.getElementById('newCommentText');

  async function openComments(itemId, scrollToCommentId = null) {
    currentItemId = itemId;
    commentsList.innerHTML = '<p class="loading">Loading comments...</p>';
    document.getElementById('commentsModal').classList.add('active');

    try {
      const res = await fetch(`get_user_comments.php?item_id=${itemId}`);
      const comments = await res.json();
      if(!Array.isArray(comments) || comments.length === 0) commentsList.innerHTML = '<p>No comments yet.</p>';
      else {
        commentsList.innerHTML = '';
        comments.forEach(c => {
          const div = document.createElement('div');
          div.classList.add('comment-item');
          div.id = `comment-${c.id}`;

          // Avatar links now point to public_profile.php
          const avatarWrap = document.createElement('div');
          avatarWrap.className = 'comment-avatar';
          const a = document.createElement('a');
          a.href = `public_profile.php?user_id=${c.user_id}`;
          a.className = 'avatar-link';
          const img = document.createElement('img');
          img.src = c.profile_image || 'uploads/default_avatar.png';
          img.alt = c.username || 'User';
          a.appendChild(img);
          avatarWrap.appendChild(a);

          const content = document.createElement('div');
          content.className = 'comment-content';
          content.innerHTML = `
            <div class="comment-author"><a href="public_profile.php?user_id=${c.user_id}" class="author-link">${c.username || 'User'}</a></div>
            <div class="comment-text">${escapeHtml(c.comment || '')}</div>
            <div class="comment-date">${c.date_posted ? new Date(c.date_posted).toLocaleString() : ''}</div>
          `;

          div.appendChild(avatarWrap);
          div.appendChild(content);
          commentsList.appendChild(div);
        });

        if(scrollToCommentId) {
          const el = document.getElementById(`comment-${scrollToCommentId}`);
          if(el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }
    } catch (err) {
      commentsList.innerHTML = '<p>Failed to load comments.</p>';
      console.error('Failed to load comments', err);
    }
  }

  document.getElementById('commentSubmitBtn').addEventListener('click', submitComment);

  async function submitComment() {
    const text = newCommentText.value.trim();
    if(!text) return alert('Enter a comment.');
    if(!currentItemId) return alert('No item selected.');

    try {
      const res = await fetch('post_comment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({item_id: currentItemId, comment: text})
      });
      const data = await res.json();
      if(data.success){
        newCommentText.value = '';
        openComments(currentItemId);
        loadNotifications(); // update notifications for item owner
      } else {
        alert(data.error || 'Failed to post comment.');
      }
    } catch (err) {
      alert('Error posting comment.');
      console.error('Error posting comment', err);
    }
  }

  function escapeHtml(unsafe) {
    return String(unsafe)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // Notifications
  async function loadNotifications() {
    try {
      const res = await fetch('get_notifications.php');
      const data = await res.json();
      if(!data.success) return;

      notifications = data.notifications || [];
      const unreadCount = notifications.filter(n => !n.is_read).length;
      notificationCount.textContent = unreadCount > 0 ? unreadCount : '0';

      notificationList.innerHTML = '';
      notifications.forEach(n => {
        const div = document.createElement('div');
        div.classList.add('notification-item');
        div.innerHTML = `
          <div class="notif-msg">${escapeHtml(n.message)}</div>
          <small>${n.date_created ? new Date(n.date_created).toLocaleString() : ''}</small>
        `;
        div.addEventListener('click', async () => {
          try { await fetch('mark_notifications_read.php'); } catch(e){}
          notificationCount.textContent = '0';
          notificationList.classList.remove('active');
          openComments(n.item_id, n.comment_id);
        });
        notificationList.appendChild(div);
      });
    } catch (err) {
      notificationCount.textContent = '0';
      console.error('Failed to load notifications', err);
    }
  }

  notificationBell.addEventListener('click', async () => {
    notificationList.classList.toggle('active');
    if(notificationList.classList.contains('active')) {
      try { await fetch('mark_notifications_read.php'); } catch(e){}
      notificationCount.textContent = '0';
    }
  });

  searchInput.addEventListener('input', displayItems);
  filterCategory.addEventListener('change', displayItems);
  filterType.addEventListener('change', displayItems);

  loadItems();
  loadNotifications();
});
</script>
</body>
</html>
