let topics = [];

const newTopicForm =
  document.getElementById("new-topic-form");

const topicListContainer =
  document.getElementById("topic-list-container");

function createTopicArticle(topic) {

  const article =
    document.createElement("article");

  article.innerHTML = `
    <h3>
      <a href="topic.html?id=${topic.id}">
        ${topic.subject}
      </a>
    </h3>

    <footer>
      Posted by: ${topic.author}
      on ${topic.created_at}
    </footer>

    <div>

      <button
        class="edit-btn"
        data-id="${topic.id}"
      >
        Edit
      </button>

      <button
        class="delete-btn"
        data-id="${topic.id}"
      >
        Delete
      </button>

    </div>
  `;

  return article;
}

function renderTopics() {

  topicListContainer.innerHTML = "";

  topics.forEach(topic => {

    topicListContainer.appendChild(
      createTopicArticle(topic)
    );

  });
}

async function handleCreateTopic(event) {

  event.preventDefault();

  const subject =
    document.getElementById("topic-subject")
      .value
      .trim();

  const message =
    document.getElementById("topic-message")
      .value
      .trim();

  const submitButton =
    document.getElementById("create-topic");

  const editId =
    submitButton.dataset.editId;

  if (editId) {

    await handleUpdateTopic(
      editId,
      {
        subject,
        message
      }
    );

    submitButton.textContent =
      "Create Topic";

    delete submitButton.dataset.editId;

    newTopicForm.reset();

    return;
  }

  try {

    const response =
      await fetch("./api/index.php", {

        method: "POST",

        headers: {
          "Content-Type":
            "application/json"
        },

        body: JSON.stringify({
          subject,
          message,
          author: "Student"
        })

      });

    const result =
      await response.json();

    if (result.success) {

      topics.push({
        id: result.id,
        subject,
        message,
        author: "Student",
        created_at:
          new Date()
            .toISOString()
            .slice(0, 19)
            .replace("T", " ")
      });

      renderTopics();

      newTopicForm.reset();
    }

  } catch (error) {

    console.error(
      "Error creating topic:",
      error
    );
  }
}

async function handleUpdateTopic(
  id,
  fields
) {

  try {

    const response =
      await fetch("./api/index.php", {

        method: "PUT",

        headers: {
          "Content-Type":
            "application/json"
        },

        body: JSON.stringify({
          id,
          ...fields
        })

      });

    const result =
      await response.json();

    if (result.success) {

      topics = topics.map(topic => {

        if (
          String(topic.id) ===
          String(id)
        ) {

          return {
            ...topic,
            ...fields
          };
        }

        return topic;
      });

      renderTopics();
    }

  } catch (error) {

    console.error(
      "Error updating topic:",
      error
    );
  }
}

async function handleTopicListClick(
  event
) {

  const target = event.target;

  if (
    target.classList.contains(
      "delete-btn"
    )
  ) {

    const id =
      target.dataset.id;

    try {

      const response =
        await fetch(
          `./api/index.php?id=${id}`,
          {
            method: "DELETE"
          }
        );

      const result =
        await response.json();

      if (result.success) {

        topics = topics.filter(
          topic =>
            String(topic.id) !==
            String(id)
        );

        renderTopics();
      }

    } catch (error) {

      console.error(
        "Error deleting topic:",
        error
      );
    }
  }

  if (
    target.classList.contains(
      "edit-btn"
    )
  ) {

    const id =
      target.dataset.id;

    const topic =
      topics.find(
        topic =>
          String(topic.id) ===
          String(id)
      );

    if (!topic) return;

    document.getElementById(
      "topic-subject"
    ).value = topic.subject;

    document.getElementById(
      "topic-message"
    ).value = topic.message;

    const submitButton =
      document.getElementById(
        "create-topic"
      );

    submitButton.textContent =
      "Update Topic";

    submitButton.dataset.editId =
      topic.id;
  }
}

async function loadAndInitialize() {

  try {

    const response =
      await fetch("./api/index.php");

    const result =
      await response.json();

    if (
      result.success &&
      Array.isArray(result.data)
    ) {

      topics = result.data;

      renderTopics();
    }

  } catch (error) {

    console.error(
      "Error loading topics:",
      error
    );
  }

  newTopicForm.addEventListener(
    "submit",
    handleCreateTopic
  );

  topicListContainer.addEventListener(
    "click",
    handleTopicListClick
  );
}

loadAndInitialize();
