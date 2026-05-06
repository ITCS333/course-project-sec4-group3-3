/**
 * list.js — Course Resources List Page
 */

let resources = [];

function createResourceArticle(resource) {
  const article = document.createElement("article");
  article.innerHTML = `
    <h2>${resource.title}</h2>
    <p>${resource.description || ""}</p>
    <a href="details.html?id=${resource.id}">View Resource</a>
  `;
  return article;
}

async function loadResources() {
  const section = document.getElementById("resource-list-section");
  section.innerHTML = "";

  try {
    const res  = await fetch("./api/index.php");
    const data = await res.json();
    if (data.success && Array.isArray(data.data)) {
      resources = data.data;
      resources.forEach(r => section.appendChild(createResourceArticle(r)));
    }
  } catch (err) {
    console.error("Error loading resources:", err);
  }
}

loadResources();
