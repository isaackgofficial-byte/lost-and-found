document.addEventListener("DOMContentLoaded", () => {
  const lostContainer = document.getElementById("lostItems");
  const foundContainer = document.getElementById("foundItems");
  const themeToggle = document.querySelector(".theme-toggle");
  const searchInput = document.getElementById("search");
  const filterCategory = document.getElementById("filterCategory");
  const filterType = document.getElementById("filterType");

  // 🌙 Initialize theme
  const currentTheme = localStorage.getItem("theme") || "dark";
  document.body.classList.toggle("dark-mode", currentTheme === "dark");
  themeToggle.textContent = currentTheme === "dark" ? "🌙" : "☀️";

  themeToggle.addEventListener("click", () => {
    const isDark = document.body.classList.toggle("dark-mode");
    localStorage.setItem("theme", isDark ? "dark" : "light");
    themeToggle.textContent = isDark ? "🌙" : "☀️";
  });

  // 🚀 Fetch approved items
  let allItems = [];

  async function loadItems() {
    try {
      const response = await fetch("get_items.php");
      const items = await response.json();
      allItems = items.filter(item => item.status === "approved"); // only approved

      renderItems(allItems);
    } catch (error) {
      lostContainer.innerHTML = "<p>Failed to load items. Try again later.</p>";
      foundContainer.innerHTML = "<p>Failed to load items. Try again later.</p>";
      console.error(error);
    }
  }

  function renderItems(items) {
    lostContainer.innerHTML = "";
    foundContainer.innerHTML = "";

    if (!items.length) {
      lostContainer.innerHTML = "<p class='no-items'>No approved lost items.</p>";
      foundContainer.innerHTML = "<p class='no-items'>No approved found items.</p>";
      return;
    }

    items.forEach(item => {
      const card = document.createElement("div");
      card.className = "item-card";

      const imgSrc = item.image_path ? item.image_path : "images/default.png";
      const img = document.createElement("img");
      img.className = "item-image";
      img.src = imgSrc;
      img.alt = item.title;
      img.addEventListener("click", () => showImagePopup(imgSrc));

      const details = document.createElement("div");
      details.className = "item-details";
      details.innerHTML = `
        <h3>${item.title}</h3>
        <p><strong>Category:</strong> ${item.category}</p>
        <p><strong>Type:</strong> ${item.type}</p>
        <p>${item.description}</p>
        <p><strong>Posted by:</strong> ${item.username}</p>
        <p><strong>Date:</strong> ${new Date(item.created_at).toLocaleDateString()}</p>
      `;

      // Comment section
      const viewCommentsBtn = document.createElement("button");
      viewCommentsBtn.className = "status-btn status-approved";
      viewCommentsBtn.textContent = "View Comments";
      viewCommentsBtn.addEventListener("click", () => loadComments(item.id, card));

      details.appendChild(viewCommentsBtn);
      card.appendChild(img);
      card.appendChild(details);

      if (item.type === "Lost") {
        lostContainer.appendChild(card);
      } else {
        foundContainer.appendChild(card);
      }
    });
  }

  // 💬 Load user comments for an item
  async function loadComments(itemId, card) {
    try {
      let commentBox = card.querySelector(".comment-box");
      if (!commentBox) {
        commentBox = document.createElement("div");
        commentBox.className = "comment-box";
        card.appendChild(commentBox);
      }

      const res = await fetch(`get_user_comments.php?item_id=${itemId}`);
      const comments = await res.json();

      if (comments.error) {
        commentBox.innerHTML = `<p>${comments.error}</p>`;
      } else if (comments.length === 0) {
        commentBox.innerHTML = `<p>No comments yet.</p>`;
      } else {
        commentBox.innerHTML = comments
          .map(
            c => `<p><strong>${c.username}:</strong> ${c.comment} <em>(${new Date(c.created_at).toLocaleString()})</em></p>`
          )
          .join("");
      }

      commentBox.style.display =
        commentBox.style.display === "none" || commentBox.style.display === ""
          ? "block"
          : "none";
    } catch (error) {
      alert("Failed to load comments.");
      console.error(error);
    }
  }

  // 🔎 Live search and filter
  function filterItems() {
    const query = searchInput.value.toLowerCase();
    const category = filterCategory.value;
    const type = filterType.value;

    const filtered = allItems.filter(item => {
      const matchesQuery =
        item.title.toLowerCase().includes(query) ||
        item.description.toLowerCase().includes(query) ||
        item.category.toLowerCase().includes(query);
      const matchesCategory = category === "All" || item.category === category;
      const matchesType = type === "All" || item.type === type;
      return matchesQuery && matchesCategory && matchesType;
    });

    renderItems(filtered);
  }

  searchInput.addEventListener("input", filterItems);
  filterCategory.addEventListener("change", filterItems);
  filterType.addEventListener("change", filterItems);

  // 🖼 Image popup
  const popupOverlay = document.createElement("div");
  popupOverlay.className = "popup-overlay";
  const popupContent = document.createElement("div");
  popupContent.className = "popup-content";
  popupOverlay.appendChild(popupContent);
  document.body.appendChild(popupOverlay);

  function showImagePopup(src) {
    popupContent.innerHTML = `<img src="${src}" style="max-width:100%;border-radius:8px;"> <button onclick="closePopup()" class="close-popup">×</button>`;
    popupOverlay.style.display = "flex";
  }

  window.closePopup = () => {
    popupOverlay.style.display = "none";
    popupContent.innerHTML = "";
  };

  // Initial load
  loadItems();
});
