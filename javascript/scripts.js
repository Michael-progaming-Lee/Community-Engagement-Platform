// Fetch and display discussions
async function fetchDiscussions() {
    const response = await fetch('forum_api.php?action=fetch');
    const data = await response.json();
    displayDiscussions(data);
}

// Display discussions
function displayDiscussions(discussions) {
    const list = document.getElementById('discussionList');
    list.innerHTML = '';
    discussions.forEach(discussion => {
        const div = document.createElement('div');
        div.innerHTML = `
            <h3 onclick="viewDiscussion(${discussion.id})">${discussion.title}</h3>
            <p>Author: ${discussion.author}</p>
            <button onclick="showUpdateForm(${discussion.id})">Edit</button>
            <button onclick="deleteDiscussion(${discussion.id})">Delete</button>
        `;
        list.appendChild(div);
    });
}

// Search discussions
function searchDiscussions() {
    const query = document.getElementById('searchInput').value;
    fetch(`forum_api.php?action=search&query=${query}`)
        .then(response => response.json())
        .then(data => displayDiscussions(data));
}

// Add a new discussion
function addDiscussion() {
    const title = document.getElementById('discussionTitle').value;
    const author = document.getElementById('discussionAuthor').value;
    const content = document.getElementById('discussionContent').value;

    fetch('forum_api.php?action=add', {
        method: 'POST',
        body: JSON.stringify({ title, author, content })
    }).then(() => fetchDiscussions());
}

// Show discussion details with comments
function viewDiscussion(id) {
    fetch(`forum_api.php?action=view&id=${id}`)
        .then(response => response.json())
        .then(data => {
            const detail = document.getElementById('discussionDetail');
            detail.innerHTML = `
                <h2>${data.title}</h2>
                <p>${data.content}</p>
                <h3>Comments</h3>
                <div id="commentsList"></div>
                <input type="text" id="commentAuthor" placeholder="Author">
                <textarea id="commentContent" placeholder="Comment"></textarea>
                <button onclick="addComment(${id})">Add Comment</button>
            `;
            displayComments(data.comments);
        });
}

// Display comments for a discussion
function displayComments(comments) {
    const list = document.getElementById('commentsList');
    list.innerHTML = '';
    comments.forEach(comment => {
        const div = document.createElement('div');
        div.innerHTML = `<p>${comment.author}: ${comment.comment}</p>`;
        list.appendChild(div);
    });
}

// Add comment to a discussion
function addComment(discussionId) {
    const author = document.getElementById('commentAuthor').value;
    const comment = document.getElementById('commentContent').value;

    fetch('forum_api.php?action=addComment', {
        method: 'POST',
        body: JSON.stringify({ discussionId, author, comment })
    }).then(() => viewDiscussion(discussionId));
}

// Edit, update, and delete functions to be implemented similarly...
