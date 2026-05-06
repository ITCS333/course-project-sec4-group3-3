/**
 * admin.js — Manage Resources Admin Page
 */

let resources = [];
let editingId = null;

const resourceForm   = document.getElementById("resource-form");
const resourcesTbody = document.getElementById("resources-tbody");

function createResourceRow(resource) {
  const tr = document.createElement("tr");
  tr.innerHTML = `
    <td>${resource.title}</td>
    <td>${resource.description || ""}</td>
    <td>${resource.link}</td>
    <td>
      <button class="edit-btn"   data-id="${resource.id}">Edit</button>
      <button class="delete-btn" data-id="${resource.id}">Delete</button>
    </td>
  `;
  return tr;
}

function renderTable(arr) {
  resourcesTbody.innerHTML = "";
  const list = arr !== undefined ? arr : resources;
  list.forEach(r => resourcesTbody.appendChild(createResourceRow(r)));
}

function handleAddResource(event) {
  event.preventDefault();

  const title       = document.getElementById("resource-title").value.trim();
  const description = document.getElementById("resource-description").value.trim();
  const link        = document.getElementById("resource-link").value.trim();

  if (editingId) {
    fetch("./api/index.php", {
      method:  "PUT",
      headers: { "Content-Type": "application/json" },
      body:    JSON.stringify({ id: editingId, title, description, link }),
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          const idx = resources.findIndex(r => String(r.id) === String(editingId));
          if (idx !== -1) resources[idx] = { ...resources[idx], title, description, link };
          editingId = null;
          document.getElementById("add-resource").textContent = "Add Resource";
          resourceForm.reset();
          renderTable();
        } else {
          alert(data.message || "Failed to update.");
        }
      })
      .catch(() => alert("Network error."));
  } else {
    fetch("./api/index.php", {
      method:  "POST",
      headers: { "Content-Type": "application/json" },
      body:    JSON.stringify({ title, description, link }),
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          resources.push({ id: data.id, title, description, link });
          renderTable();
          resourceForm.reset();
        } else {
          alert(data.message || "Failed to add resource.");
        }
      })
      .catch(() => alert("Network error."));
  }
}

function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains("delete-btn")) {
    const id = target.dataset.id;
    if (!confirm("Delete this resource?")) return;
    fetch(`./api/index.php?id=${id}`, { method: "DELETE" })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          resources = resources.filter(r => String(r.id) !== String(id));
          renderTable();
        } else {
          alert(data.message || "Failed to delete.");
        }
      })
      .catch(() => alert("Network error."));
  }

  if (target.classList.contains("edit-btn")) {
    const id       = target.dataset.id;
    const resource = resources.find(r => String(r.id) === String(id));
    if (!resource) return;
    editingId = id;
    document.getElementById("resource-title").value       = resource.title;
    document.getElementById("resource-description").value = resource.description || "";
    document.getElementById("resource-link").value        = resource.link;
    document.getElementById("add-resource").textContent   = "Update Resource";
  }
}

async function loadAndInitialize() {
  try {
    const res  = await fetch("./api/index.php");
    const data = await res.json();
    if (data.success && Array.isArray(data.data)) {
      resources = data.data;
      renderTable();
    }
  } catch (err) { console.error(err); }

  if (!loadAndInitialize._listenersAttached) {
    loadAndInitialize._listenersAttached = true;
    if (resourceForm)   resourceForm.addEventListener("submit", handleAddResource);
    if (resourcesTbody) resourcesTbody.addEventListener("click", handleTableClick);
  }
}

loadAndInitialize._listenersAttached = false;
loadAndInitialize();
