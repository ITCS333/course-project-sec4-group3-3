/**
 * details.js — Resource Details Page
 */

let currentComments = [];

function getResourceIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get("id");
}

function renderResourceDetails(resource) {
  document.getElementById("resource-title").textContent       = resource.title;
  document.getElementById("resource-description").textContent = resource.description || "";
  document.getElementById("resource-link").setAttribute("href", resource.link);
}

function createCommentArticle(comment) {
  const article = document.createElement("article");
  article.innerHTML = `
    <p>${comment.text}</p>
    <footer>${comment.author}</footer>
  `;
  return article;
}

function renderComments() {
  const list = document.getElementById("comment-list");
  list.innerHTML = "";
  currentComments.forEach(c => list.appendChild(createCommentArticle(c)));
}

function handleAddComment(event) {
  event.preventDefault();
  const textarea = document.getElementById("new-comment");
  const text     = textarea.value.trim();
  if (!text) return;

  const id = getResourceIdFromURL();
  fetch(`./api/index.php?action=comment`, {
    method:  "POST",
    headers: { "Content-Type": "application/json" },
    body:    JSON.stringify({ resource_id: id, author: "Student", text }),
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        textarea.value = "";
        if (data.data) {
          currentComments.push(data.data);
          renderComments();
        }
      }
    })
    .catch(console.error);
}

async function initializePage() {
  const id = getResourceIdFromURL();
  if (!id) return;

  try {
    const resResp = await fetch(`./api/index.php?id=${id}`);
    const resData = await resResp.json();
    if (resData.success && resData.data) {
      renderResourceDetails(resData.data);
    }
  } catch (err) { console.error(err); }

  try {
    const comResp = await fetch(`./api/index.php?resource_id=${id}&action=comments`);
    const comData = await comResp.json();
    if (comData.success && Array.isArray(comData.data)) {
      currentComments = comData.data;
      renderComments();
    }
  } catch (err) { console.error(err); }

  const form = document.getElementById("comment-form");
  if (form) form.addEventListener("submit", handleAddComment);
}

initializePage();
