const assignmentListSection =
  document.getElementById("assignment-list-section");

function createAssignmentArticle(assignment) {

  const article =
    document.createElement("article");

  article.innerHTML = `
    <h2>${assignment.title}</h2>

    <p>
      Due: ${assignment.due_date}
    </p>

    <p>
      ${assignment.description}
    </p>

    <a href="details.html?id=${assignment.id}">
      View Details & Discussion
    </a>
  `;

  return article;
}

async function loadAssignments() {

  try {

    const response =
      await fetch("./api/index.php");

    const result =
      await response.json();

    assignmentListSection.innerHTML = "";

    if (
      result.success &&
      Array.isArray(result.data)
    ) {

      result.data.forEach(assignment => {

        assignmentListSection.appendChild(
          createAssignmentArticle(assignment)
        );

      });
    }

  } catch (error) {

    console.error(
      "Error loading assignments:",
      error
    );
  }
}

loadAssignments();
