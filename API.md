# API Documentation

## Authentication

All API requests require an API key in the header:

```
X-API-Key: your_api_key_here
```

## Base URL

```
https://yourdomain.com/api.php
```

## Endpoints

### GET /channels

List user's channels

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "channel_id": -1001234567890,
      "title": "My Channel",
      "type": "channel",
      "subscriber_count": 1000
    }
  ]
}
```

### GET /posts

List posts for a channel

**Parameters:**
- `channel_id` (required): Channel ID

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "channel_id": -1001234567890,
      "content": "Hello World",
      "posted_at": "2025-01-01 12:00:00"
    }
  ]
}
```

### POST /posts

Create a new post

**Body:**
```json
{
  "channel_id": -1001234567890,
  "content": "Hello from API!"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "post_id": 123,
    "message_id": 456
  }
}
```

### POST /schedule

Schedule a post

**Body:**
```json
{
  "channel_id": -1001234567890,
  "content": "Scheduled post",
  "schedule_time": "2025-12-25 14:00:00"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "scheduled_id": 789
  }
}
```

## Error Responses

```json
{
  "success": false,
  "error": "Error message",
  "code": 400
}
```

## Rate Limiting

- Free: 100 requests/hour
- Pro: 1000 requests/hour
- Business: Unlimited

## Examples

### cURL

```bash
curl -X POST https://yourdomain.com/api.php/posts \
  -H "X-API-Key: your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"channel_id": -1001234567890, "content": "Hello!"}'
```

### Python

```python
import requests

headers = {
    'X-API-Key': 'your_api_key',
    'Content-Type': 'application/json'
}

data = {
    'channel_id': -1001234567890,
    'content': 'Hello from Python!'
}

response = requests.post(
    'https://yourdomain.com/api.php/posts',
    headers=headers,
    json=data
)

print(response.json())
```

### PHP

```php
$apiKey = 'your_api_key';
$url = 'https://yourdomain.com/api.php/posts';

$data = [
    'channel_id' => -1001234567890,
    'content' => 'Hello from PHP!'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: ' . $apiKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
curl_close($ch);

print_r(json_decode($response, true));
```
