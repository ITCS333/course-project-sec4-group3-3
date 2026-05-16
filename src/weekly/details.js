/**
 * details.js — Week Details Page
 */
 
let currentWeekId   = null;
let currentComments = [];
 
const weekTitle       = document.getElementById("week-title");
const weekStartDate   = document.getElementById("week-start-date");
const weekDescription = document.getElementById("week-description");
const weekLinksList   = document.getElementById("week-links-list");
const commentList     = document.getElementById("comment-list");
const commentForm     = document.getElementById("comment-form");
const newCommentInput = document.getElementById("new-comment");
 
function getWeekIdFromURL() {
  return new URLSearchParams(window.location.search).get("id");
}
 
function renderWeekDetails(week) {
  weekTitle.textContent       = week.title;
  weekStartDate.textContent   = "Starts on: " + week.start_date;
  weekDescription.textContent = week.description || "";
 
  weekLinksList.innerHTML = "";
  const links = Array.isArray(week.links) ? week.links : [];
  links.forEach(url => {
    const li = document.createElement("li");
    li.innerHTML = `<a href="${url}" target="_blank">${url}</a>`;
    weekLinksList.appendChild(li);
  });
}
 
function createCommentArticle(comment) {
  const article = document.createElement("article");
  article.innerHTML = `
    <p>${comment.text}</p>
    <footer>Posted by: ${comment.author}</footer>
  `;
  return article;
}
 
function renderComments() {
  commentList.innerHTML = "";
  currentComments.forEach(c => commentList.appendChild(createCommentArticle(c)));
}
 
async function handleAddComment(event) {
  event.preventDefault();
  const text = newCommentInput.value.trim();
  if (!text) return;
 
  try {
    const res  = await fetch("./api/index.php?action=comment", {
      method:  "POST",
      headers: { "Content-Type": "application/json" },
      body:    JSON.stringify({ week_id: currentWeekId, author: "Student", text }),
    });
    const data = await res.json();
    if (data.success) {
      newCommentInput.value = "";
      if (data.data) {
        currentComments.push(data.data);
        renderComments();
      }
    }
  } catch (err) { console.error(err); }
}
 
async function initializePage() {
  currentWeekId = getWeekIdFromURL();
  if (!currentWeekId) {
    weekTitle.textContent = "Week not found.";
    return;
  }
 
  try {
    const [weekResp, comResp] = await Promise.all([
      fetch(`./api/index.php?id=${currentWeekId}`),
      fetch(`./api/index.php?action=comments&week_id=${currentWeekId}`),
    ]);
    const weekData = await weekResp.json();
    const comData  = await comResp.json();
 
    if (weekData.success && weekData.data) {
      renderWeekDetails(weekData.data);
    } else {
      weekTitle.textContent = "Week not found.";
    }
 
    currentComments = (comData.success && Array.isArray(comData.data)) ? comData.data : [];
    renderComments();
 
    if (commentForm) commentForm.addEventListener("submit", handleAddComment);
  } catch (err) { console.error(err); }
}
 
initializePage();