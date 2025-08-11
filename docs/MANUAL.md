# WP AI Agent Manual
WhatsApp-style AI chat popup with quote generation for WordPress.

## Table of Contents
- [Overview](#overview)
- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [API Mode Details](#api-mode-details)
- [Admin "Test Chat"](#admin-test-chat)
- [Frontend Usage](#frontend-usage)
- [UX Details](#ux-details)
- [Styling & Customisation](#styling--customisation)
- [Privacy & Security](#privacy--security)
- [Troubleshooting](#troubleshooting)

## Overview
WP AI Agent adds a responsive chat popup to your site that talks to OpenAI or an alternate API and can even return quote JSON. The widget can be placed via shortcode or Elementor and works on desktop and mobile.

**Requirements**
- WordPress 6.4+
- PHP 8.1+

## Features
- Popup chat widget
- Admin "Test Chat" tab
- **Enter-to-Send** (Shift+Enter inserts newline, IME-safe)
- **Typing indicator** (`<AgentName> is typing…` with animated dots)
- **Per-session Australian agent name** chosen from: Jack Wilson, Olivia Nguyen, Liam O’Connor, Chloe Smith, Noah Patel
- Elementor widget and shortcode

## Installation
1. Upload the `wp-ai-agent` folder to your `/wp-content/plugins/` directory.
2. Activate **WP AI Agent** from **Plugins > Installed Plugins**.
3. Users need the `manage_options` capability to configure settings.
4. To update, replace the plugin folder or use the WordPress updater. Back up your site before updating.

## Configuration
Navigate to **Admin > AI Agent > Settings**. Available options:

| Setting | Type | Default | Description |
| ------- | ---- | ------- | ----------- |
| OpenAI API Key | text | — | Primary API key for OpenAI requests |
| Alternate API Key (Probex) | text | — | Uses Probex instead of OpenAI when provided |
| Base URL (for Probex) | url | `https://api.probex.top/v1/chat/completions` | Custom endpoint for alternate API |
| Model selector | select | `gpt-4o-mini` | Available only when Alternate API is set |
| Chatbox autostart | toggle | off | Automatically open the chat on page load |
| Chatbox position | select | `right` | `right` or `left` corner for floating button |
| Welcome message | text | "" | Optional greeting shown when chat opens |
| Name pool behaviour | toggle | on | Choose one of five AU names per browser session |

## API Mode Details
WP AI Agent supports two modes:
- **OpenAI** – default mode, using your OpenAI API key.
- **Alternate (Probex)** – activated when an Alternate API key is entered.

When using Probex, set the Base URL, for example:
`https://api.probex.top/v1/chat/completions`

Requests are sent as JSON and responses stream back token by token. If the API does not support streaming, the full response is returned when complete.

## Admin "Test Chat"
Found under **AI Agent > Test Chat**. Use it to verify connectivity without exposing the widget publicly.

- **Start a New Chat** clears the current conversation.
- Enter-to-send and the typing indicator behave the same as on the frontend.

## Frontend Usage
### Shortcode
Use `[wpai_chat]` in posts or pages. Attributes:
- `button="true|false"`
- `position="right|left"`
- `label="Chat"`
- `color="#25D366"`

### Elementor
1. Edit a page with Elementor.
2. Search for **AI Agent Chat** widget.
3. Drag it into your layout and adjust position, label, and colour.

**Placement tips:** place the widget near the footer or use the floating button for site-wide access.

## UX Details
- Press **Enter** to send; **Shift+Enter** creates a new line.
- IME safety: typing is ignored while composition is active (important for Chinese, Japanese, Korean input).
- Typing indicator shows "`<AgentName> is typing…`" with three animated dots until a reply is received or an error occurs.
- On each new browser session, one Australian name is selected from the pool and displayed in the chat header and indicator.

## Styling & Customisation
Key CSS hooks:
- `.ai-agent-button` – floating launcher
- `.ai-agent-window` – chat container
- `.ai-agent-msg` – message bubbles (`.user` and `.bot` modifiers)
- `.wpai-typing` – typing indicator container
- `.wpai-dot` – animated dot element

Override styles by adding custom CSS in your theme or child theme, or via the Customizer.

## Privacy & Security
API keys are stored in encrypted WordPress options and masked in the admin UI. Chat messages are sent to the configured API and stored in the site's database based on your retention setting.

For GDPR compliance, inform users that messages are sent to third-party AI services. Use consent banners where required.

## Troubleshooting
- **Widget button doesn’t open** – Ensure jQuery is loading and no JavaScript errors appear in the browser console. Conflicting themes or scripts may block the popup.
