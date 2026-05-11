let currentAssignmentId = null;
let currentComments = [];

const assignmentTitle =
  document.getElementById("assignment-title");

const assignmentDueDate =
  document.getElementById("assignment-due-date");

const assignmentDescription =
  document.getElementById("assignment-description");

const assignmentFilesList =
  document.getElementById("assignment-files-list");

const commentList =
  document.getElementById("comment-list");

const commentForm =
  document.getElementById("comment-form");

const newCommentInput =
  document.getElementById("new-comment");

function getAssignmentIdFromURL() {

  const params =
    new URLSearchParams(window.location.search);

  return params.get("id");
}

function renderAssignmentDetails(assignment) {

  assignmentTitle.textContent =
    assignment.title;

  assignmentDueDate.textContent =
    `Due: ${assignment.due_date}`;

  assignmentDescription.textContent =
    assignment.description;

  assignmentFilesList.innerHTML = "";

  assignment.files.forEach(file => {

    const li =
      document.createElement("li");

    const a =
      document.createElement("a");

    a.href = file;

    a.textContent = file;

    a.target = "_blank";

    li.appendChild(a);

    assignmentFilesList.appendChild(li);

  });
}

function createCommentArticle(comment) {

  const article =
    document.createElement("article");

  article.innerHTML = `
    <p>${comment.text}</p>
    <footer>
      Posted by: ${comment.author}
    </footer>
  `;

  return article;
}

function renderComments() {

  commentList.innerHTML = "";

  currentComments.forEach(comment => {

    commentList.appendChild(
      createCommentArticle(comment)
    );

  });
}

async function handleAddComment(event) {

  event.preventDefault();

  const commentText =
    newCommentInput.value.trim();

  if (!commentText) return;

  const response = await fetch(
    "./api/index.php?action=comment",
    {

      method: "POST",

      headers: {
        "Content-Type": "application/json"
      },

      body: JSON.stringify({
        assignment_id: currentAssignmentId,
        author: "Student",
        text: commentText
      })

    }
  );

  const result = await response.json();

  if (result.success) {

    currentComments.push(result.data);

    renderComments();

    newCommentInput.value = "";
  }
}

async function initializePage() {

  currentAssignmentId =
    getAssignmentIdFromURL();

  if (!currentAssignmentId) {

    assignmentTitle.textContent =
      "Assignment not found.";

    return;
  }

  const [
    assignmentResponse,
    commentsResponse
  ] = await Promise.all([

    fetch(
      `./api/index.php?id=${currentAssignmentId}`
    ),

    fetch(
      `./api/index.php?action=comments&assignment_id=${currentAssignmentId}`
    )

  ]);

  const assignmentResult =
    await assignmentResponse.json();

  const commentsResult =
    await commentsResponse.json();

  currentComments =
    commentsResult.data || [];

  if (
    assignmentResult.success &&
    assignmentResult.data
  ) {

    renderAssignmentDetails(
      assignmentResult.data
    );

    renderComments();

    commentForm.addEventListener(
      "submit",
      handleAddComment
    );

  } else {

    assignmentTitle.textContent =
      "Assignment not found.";
  }
}

initializePage();
