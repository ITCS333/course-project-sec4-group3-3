/**
 * list.js — Weekly Course Breakdown List Page
 */
 
const weekListSection = document.getElementById("week-list-section");
 
function createWeekArticle(week) {
  const article = document.createElement("article");
  article.innerHTML = `
    <h2>${week.title}</h2>
    <p>Starts on: ${week.start_date}</p>
    <p>${week.description || ""}</p>
    <a href="details.html?id=${week.id}">View Details & Discussion</a>
  `;
  return article;
}
 
async function loadWeeks() {
  weekListSection.innerHTML = "";
  try {
    const res  = await fetch("./api/index.php");
    const data = await res.json();
    if (data.success && Array.isArray(data.data)) {
      data.data.forEach(w => weekListSection.appendChild(createWeekArticle(w)));
    }
  } catch (err) { console.error(err); }
}
 
loadWeeks();