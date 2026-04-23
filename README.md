# DokuWiki Plugin: localopen

Open local files and folders directly from DokuWiki pages — **without browser popups or protocol handlers**.

This plugin replaces `localexplorer://`-style links with a modern, secure approach using a local HTTP service and background requests (`fetch()`), resulting in a seamless, one-click experience.

---

## ✨ Features

- Open local files (`.docx`, `.pdf`, etc.) and folders from wiki links
- No browser confirmation dialogs
- No blank tabs or navigation away from the wiki
- Works with:
  - Local drives (`C:\`, `Z:\`, etc.)
  - Network shares (`\\server\share`)
- Uses standard HTTP instead of custom protocol handlers
- Lightweight and fast

---

## 🧠 How It Works

1. The plugin renders links like:  \[\[localopen>c:\windows|Windows\]\]

2. When clicked:
- A background `fetch()` request is sent to:

  ```
  http://127.0.0.1:2222/open?path=...&token=...
  ```
- A small Python service running locally receives the request
- The service opens the file using Windows (`os.startfile`)

👉 The browser never navigates away from the page.

---

## 📦 Requirements

- DokuWiki
- Python 3 (Windows)
- A local Python service (included below)

---

## 🔧 Installation

### 1. Install the Plugin

Copy the plugin into:  lib/plugins/localopen/

---

### 2. Set Your Token

Choose a secret token (any random string)


Update it in:

- the plugin PHP file
- the Python service

---

### 3. Run the Local Python Service

Save this as `localopen_service.py`:

```python
from http.server import BaseHTTPRequestHandler, HTTPServer
from urllib.parse import urlparse, parse_qs, unquote
import os

HOST = "127.0.0.1"
PORT = 2222
TOKEN = "ChangeThisToYourToken"

ALLOWED_PREFIXES = [
    r"C:\\",
    r"D:\\",
    r"Z:\\",
    r"\\10.2.1.74\\",
]

def normalize_path(path):
    path = unquote(path)
    return path.strip().strip('"').strip("'")

def is_allowed(path):
    p = path.lower()
    return any(p.startswith(prefix.lower()) for prefix in ALLOWED_PREFIXES)

class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        parsed = urlparse(self.path)

        if parsed.path != "/open":
            self.send_error(404)
            return

        qs = parse_qs(parsed.query)
        token = qs.get("token", [""])[0]
        path = normalize_path(qs.get("path", [""])[0])

        if token != TOKEN:
            self.send_error(403)
            return

        if not is_allowed(path):
            self.send_error(403)
            return

        if not os.path.exists(path):
            self.send_error(404)
            return

        os.startfile(path)

        self.send_response(200)
        self.end_headers()

    def log_message(self, *args):
        pass

HTTPServer((HOST, PORT), Handler).serve_forever()

