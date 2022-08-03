# New Post Webhook

Sends a webhook request when a new post is published.

## Instructions

 - Activate the plugin
 - Navigate to **Settings** > **Writing** settings and add the webhook URL.
 - It'll send the webhook to that URL when a new post is created.

## Installing with composer

```bash
composer require wedevs/new-post-webhook
```

## Request Format

The following format will be posted in the webhook.

```json
{
  "id": 823,
  "title": "Post Test",
  "url": "https://wp.test/post-test/",
  "content": "\n<p>Hello There</p>\n",
  "excerpt": "Hello There",
  "tags": [
    "one",
    "two"
  ],
  "categories": [
    "Uncategorized"
  ],
  "author": {
    "name": "admin",
    "url": "https://wp.test/author/admin/"
  },
  "date": {
    "raw": "2022-08-03 17:06:22",
    "formatted": "August 3, 2022"
  }
}
```
