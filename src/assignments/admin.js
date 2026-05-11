let assignments = [];

const assignmentForm = document.getElementById("assignment-form");
const assignmentsTbody = document.getElementById("assignments-tbody");

function createAssignmentRow(assignment) {

  const tr = document.createElement("tr");

  tr.innerHTML = `
    <td>${assignment.title}</td>
    <td>${assignment.due_date}</td>
    <td>${assignment.description}</td>
    <td>
      <button class="edit-btn" data-id="${assignment.id}">
        Edit
      </button>

      <button class="delete-btn" data-id="${assignment.id}">
        Delete
      </button>
    </td>
  `;

  return tr;
}

function renderTable() {

  assignmentsTbody.innerHTML = "";

  assignments.forEach(assignment => {

    assignmentsTbody.appendChild(
      createAssignmentRow(assignment)
    );

  });
}

async function handleAddAssignment(event) {

  event.preventDefault();

  const title =
    document.getElementById("assignment-title")
      .value
      .trim();

  const due_date =
    document.getElementById("assignment-due-date")
      .value
      .trim();

  const description =
    document.getElementById("assignment-description")
      .value
      .trim();

  const files =
    document.getElementById("assignment-files")
      .value
      .split("\n")
      .map(file => file.trim())
      .filter(file => file !== "");

  const submitButton =
    document.getElementById("add-assignment");

  const editId =
    submitButton.dataset.editId;

  if (editId) {

    await handleUpdateAssignment(editId, {
      title,
      due_date,
      description,
      files
    });

    return;
  }

  const response = await fetch("./api/index.php", {

    method: "POST",

    headers: {
      "Content-Type": "application/json"
    },

    body: JSON.stringify({
      title,
      due_date,
      description,
      files
    })

  });

  const result = await response.json();

  if (result.success) {

    assignments.push({
      id: result.id,
      title,
      due_date,
      description,
      files
    });

    renderTable();

    assignmentForm.reset();
  }
}

async function handleUpdateAssignment(id, fields) {

  const response = await fetch("./api/index.php", {

    method: "PUT",

    headers: {
      "Content-Type": "application/json"
    },

    body: JSON.stringify({
      id,
      ...fields
    })

  });

  const result = await response.json();

  if (result.success) {

    assignments = assignments.map(assignment => {

      if (String(assignment.id) === String(id)) {

        return {
          ...assignment,
          ...fields
        };
      }

      return assignment;
    });

    renderTable();

    assignmentForm.reset();

    const submitButton =
      document.getElementById("add-assignment");

    submitButton.textContent =
      "Add Assignment";

    delete submitButton.dataset.editId;
  }
}

async function handleTableClick(event) {

  const target = event.target;

  if (target.classList.contains("delete-btn")) {

    const id = target.dataset.id;

    const response = await fetch(
      `./api/index.php?id=${id}`,
      {
        method: "DELETE"
      }
    );

    const result = await response.json();

    if (result.success) {

      assignments = assignments.filter(
        assignment =>
          String(assignment.id) !== String(id)
      );

      renderTable();
    }
  }

  if (target.classList.contains("edit-btn")) {

    const id = target.dataset.id;

    const assignment = assignments.find(
      assignment =>
        String(assignment.id) === String(id)
    );

    if (!assignment) return;

    document.getElementById("assignment-title")
      .value = assignment.title;

    document.getElementById("assignment-due-date")
      .value = assignment.due_date;

    document.getElementById("assignment-description")
      .value = assignment.description;

    document.getElementById("assignment-files")
      .value = assignment.files.join("\n");

    const submitButton =
      document.getElementById("add-assignment");

    submitButton.textContent =
      "Update Assignment";

    submitButton.dataset.editId =
      assignment.id;
  }
}

async function loadAndInitialize() {

  const response =
    await fetch("./api/index.php");

  const result =
    await response.json();

  if (
    result.success &&
    Array.isArray(result.data)
  ) {

    assignments = result.data;

    renderTable();
  }

  assignmentForm.addEventListener(
    "submit",
    handleAddAssignment
  );

  assignmentsTbody.addEventListener(
    "click",
    handleTableClick
  );
}

loadAndInitialize();
