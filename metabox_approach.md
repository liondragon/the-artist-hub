# Proposal: Metabox-Driven Content Templates

## Overview
This proposal suggests moving the informational "FAQ" sections of Quotes (e.g., "Dust Containment", "Finish Options") out of the main `post_content` editor and into a structured "Quote Sections" metabox.

This utilizes a **"Default vs Custom" Toggle** system to combine the benefits of central management with the flexibility of local customization.

---

## How It Will Work

### 1. The Backend UI (Editor)
We will disable the default editor's initial template population and instead display a new **"Quote Info Sections"** area below the main editor.

This area will list the standard sections (Finish, Dust, Cleaning, etc.). Each section will have:
*   **Header**: Title of the section + "Move Up/Down" controls.
*   **Mode Toggle**: A switch between **"Global (Default)"** and **"Custom"**.
*   **Editor Area**:
    *   **In Global Mode**: Displays a read-only preview (or simply a label) indicating the content is being pulled from the central template file.
    *   **In Custom Mode**: Unlocks a standard WordPress WYSIWYG editor (`wp_editor`) pre-filled with the current content, allowing full editing.

### 2. Data Storage
Instead of one giant HTML string in `post_content`, data is stored in **Post Meta**:
*   `_tah_section_{id}_mode`: Stores 'default' or 'custom'.
*   `_tah_section_{id}_content`: Stores the *custom* HTML (only used if mode is custom).
*   `_tah_section_{id}_order`: Stores the sort order.

### 3. Frontend Rendering
We will hook into the content display (or use a specific template function). The rendering logic will:
1.  Fetch the list of active sections.
2.  Check the `mode` for each.
3.  **If Global**: `include` the corresponding HTML file from `assets/templates/quotes/parts/`.
4.  **If Custom**: Output the saved meta content.
5.  Wrap everything in the consistent collapsible HTML structure automatically.

---

## Why It's a Good Fit

### 1. Structure & Safety (Anti-Breakage)
*   **Problem**: In the current system, users can accidentally delete a closing `</div>` or mess up the "Collapsible" HTML structure while editing text.
*   **Solution**: With metaboxes, the user **never touches the layout HTML**. They only edit the *content* inside. The code handles the collapsible wrappers reliably every time.

### 2. True Central Management
*   **Problem**: Currently, once a quote is created, it's "disconnected" from the template. Updating `hardwood.html` doesn't fix typos in existing quotes.
*   **Solution**: By keeping the toggle on "Global", all quotes effectively reference the *live* file. Update the file once, and 500+ quotes are instantly updated (unless they were specifically customized).

### 3. Clear User Experience
*   It distinguishes "Pricing/Scope" (Main Editor) from "Standard Info" (Metaboxes).
*   The "Global/Custom" toggle makes it explicit when you are deviating from the company standard.

### 4. Zero Clutter
*   The main editor is reserved for the unique, variable parts of the quote (Scope of Work, Price), keeping it clean and focused.

---

## Cons & Limitations

### 1. Development Complexity
*   **Con**: Requires writing a custom class to handle the Metabox UI, saving logic, and frontend rendering. It is more complex than just "pasting HTML".
*   **Mitigation**: Once written, it is very low maintenance.

### 2. "Preview" Disconnect
*   **Con**: Since the content is in boxes below the main editor, it might not "feel" like writing a document flow. You don't see the full quote sequentially in one box.
*   **Mitigation**: The sections are usually static info, so "reading flow" is less critical than "data accuracy".

### 3. Data Fragmentation
*   **Con**: Content is split between `post_content` and `post_meta`. If you switch themes or disable the plugin/code in the future, the FAQ sections would disappear (whereas currently, they are just part of the post).
*   **Mitigation**: This is a custom functionality for a specific business need, so theme dependency is expected.
