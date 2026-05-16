/**
 * admin.js — Manage Weekly Breakdown
 */

let weeks = [];

const weekForm   = document.getElementById("week-form");
const weeksTbody = document.getElementById("weeks-tbody");

function createWeekRow(week) {
  const tr = document.createElement("tr");
  tr.innerHTML = `
    <td>${week.title}</td>
    <td>${week.start_date}</td>
    <td>${week.description || ""}</td>
    <td>
      <button class="edit-btn"   data-id="${week.id}">Edit</button>
      <button class="delete-btn" data-id="${week.id}">Delete</button>
    </td>
  `;
  return tr;
}

function renderTable() {
  weeksTbody.innerHTML = "";
  weeks.forEach(w => weeksTbody.appendChild(createWeekRow(w)));
}

async function handleUpdateWeek(id, fields) {
  const res  = await fetch("./api/index.php", {
    method:  "PUT",
    headers: { "Content-Type": "application/json" },
    body:    JSON.stringify({ id, ...fields }),
  });
  const data = await res.json();
  if (data.success) {
    const idx = weeks.findIndex(w => String(w.id) === String(id));
    if (idx !== -1) weeks[idx] = { ...weeks[idx], ...fields };
    renderTable();
    weekForm.reset();
    const btn = document.getElementById("add-week");
    btn.textContent = "Add Week";
    delete btn.dataset.editId;
  } else {
    alert(data.message || "Failed to update week.");
  }
}

async function handleAddWeek(event) {
  event.preventDefault();

  const title       = document.getElementById("week-title").value.trim();
  const start_date  = document.getElementById("week-start-date").value.trim();
  const description = document.getElementById("week-description").value.trim();
  const linksRaw    = document.getElementById("week-links").value.trim();
  const links       = linksRaw ? linksRaw.split("\n").map(l => l.trim()).filter(l => l) : [];

  const btn    = document.getElementById("add-week");
  const editId = btn.dataset.editId;

  if (editId) {
    await handleUpdateWeek(editId, { title, start_date, description, links });
    return;
  }

  const res  = await fetch("./api/index.php", {
    method:  "POST",
    headers: { "Content-Type": "application/json" },
    body:    JSON.stringify({ title, start_date, description, links }),
  });
  const data = await res.json();
  if (data.success) {
    weeks.push({ id: data.id, title, start_date, description, links });
    renderTable();
    weekForm.reset();
  } else {
    alert(data.message || "Failed to add week.");
  }
}

async function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains("delete-btn")) {
    const id = target.dataset.id;
    if (!confirm("Delete this week?")) return;
    const res  = await fetch(`./api/index.php?id=${id}`, { method: "DELETE" });
    const data = await res.json();
    if (data.success) {
      weeks = weeks.filter(w => String(w.id) !== String(id));
      renderTable();
    } else {
      alert(data.message || "Failed to delete.");
    }
  }

  if (target.classList.contains("edit-btn")) {
    const id   = target.dataset.id;
    const week = weeks.find(w => String(w.id) === String(id));
    if (!week) return;
    document.getElementById("week-title").value       = week.title;
    document.getElementById("week-start-date").value  = week.start_date;
    document.getElementById("week-description").value = week.description || "";
    document.getElementById("week-links").value       = Array.isArray(week.links) ? week.links.join("\n") : "";
    const btn = document.getElementById("add-week");
    btn.textContent      = "Update Week";
    btn.dataset.editId   = id;
  }
}

async function loadAndInitialize() {
  try {
    const res  = await fetch("./api/index.php");
    const data = await res.json();
    if (data.success && Array.isArray(data.data)) {
      weeks = data.data;
      renderTable();
    }
  } catch (err) { console.error(err); }

  if (weekForm)   weekForm.addEventListener("submit", handleAddWeek);
  if (weeksTbody) weeksTbody.addEventListener("click", handleTableClick);
}

loadAndInitialize();
