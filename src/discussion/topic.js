let currentTopicId = null;
let currentReplies = [];

const topicSubject =
  document.getElementById("topic-subject");

const opMessage =
  document.getElementById("op-message");

const opFooter =
  document.getElementById("op-footer");

const replyListContainer =
  document.getElementById("reply-list-container");

const replyForm =
  document.getElementById("reply-form");

const newReplyText =
  document.getElementById("new-reply");

function getTopicIdFromURL() {

  const params =
    new URLSearchParams(
      window.location.search
    );

  return params.get("id");
}

function renderOriginalPost(topic) {

  topicSubject.textContent =
    topic.subject || "";

  opMessage.textContent =
    topic.message || "";

  opFooter.textContent =
    `Posted by: ${topic.author} on ${topic.created_at}`;
}

function createReplyArticle(reply) {

  const article =
    document.createElement("article");

  article.innerHTML = `
    <p>${reply.text}</p>

    <footer>
      Posted by: ${reply.author}
      on ${reply.created_at}
    </footer>

    <div>

      <button
        class="delete-reply-btn"
        data-id="${reply.id}"
      >
        Delete
      </button>

    </div>
  `;

  return article;
}

function renderReplies() {

  replyListContainer.innerHTML = "";

  currentReplies.forEach(reply => {

    replyListContainer.appendChild(
      createReplyArticle(reply)
    );

  });
}

async function handleAddReply(event) {

  event.preventDefault();

  const replyText =
    newReplyText.value.trim();

  if (!replyText) return;

  try {

    const response =
      await fetch(
        "./api/index.php?action=reply",
        {

          method: "POST",

          headers: {
            "Content-Type":
              "application/json"
          },

          body: JSON.stringify({
            topic_id: currentTopicId,
            author: "Student",
            text: replyText
          })

        }
      );

    const result =
      await response.json();

    if (result.success) {

      if (result.data) {

        currentReplies.push(
          result.data
        );

      } else {

        currentReplies.push({
          id: result.id,
          topic_id: currentTopicId,
          text: replyText,
          author: "Student",
          created_at:
            new Date()
              .toISOString()
              .slice(0, 19)
              .replace("T", " ")
        });
      }

      renderReplies();

      newReplyText.value = "";
    }

  } catch (error) {

    console.error(
      "Error adding reply:",
      error
    );
  }
}

async function handleReplyListClick(
  event
) {

  const target = event.target;

  if (
    target.classList.contains(
      "delete-reply-btn"
    )
  ) {

    const id =
      target.dataset.id;

    try {

      const response =
        await fetch(
          `./api/index.php?action=delete_reply&id=${id}`,
          {
            method: "DELETE"
          }
        );

      const result =
        await response.json();

      if (result.success) {

        currentReplies =
          currentReplies.filter(
            reply =>
              String(reply.id) !==
              String(id)
          );

        renderReplies();
      }

    } catch (error) {

      console.error(
        "Error deleting reply:",
        error
      );
    }
  }
}

async function initializePage() {

  currentTopicId =
    getTopicIdFromURL();

  if (!currentTopicId) {

    topicSubject.textContent =
      "Topic not found.";

    return;
  }

  try {

    const [
      topicResponse,
      repliesResponse
    ] = await Promise.all([

      fetch(
        `./api/index.php?id=${currentTopicId}`
      ),

      fetch(
        `./api/index.php?action=replies&topic_id=${currentTopicId}`
      )

    ]);

    const topicResult =
      await topicResponse.json();

    const repliesResult =
      await repliesResponse.json();

    currentReplies =
      repliesResult.data || [];

    if (
      topicResult.success &&
      topicResult.data
    ) {

      renderOriginalPost(
        topicResult.data
      );

      renderReplies();

      replyForm.addEventListener(
        "submit",
        handleAddReply
      );

      replyListContainer.addEventListener(
        "click",
        handleReplyListClick
      );

    } else {

      topicSubject.textContent =
        "Topic not found.";
    }

  } catch (error) {

    console.error(
      "Error loading topic:",
      error
    );

    topicSubject.textContent =
      "Topic not found.";
  }
}

initializePage();
