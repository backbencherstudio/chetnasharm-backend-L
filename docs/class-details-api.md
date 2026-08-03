# Class Details API (updated responses)

From migration `2026_08_01_000000_add_class_details_to_classes_table`.

**New columns on `classes`:**
- `short_description` (text, nullable)
- `who_is_for` (text, nullable)
- `curriculum` (text, nullable)
- `teacher_ids` (json, nullable) — stored in DB; public responses usually expose `teachers` instead
- `is_class_recording` (0|1, default 0)

These fields change create/update bodies and list/detail responses below.

---

# Public (no auth)

## 1. `GET /api/classes`

Landing class list. Includes the new detail fields. `teacher_ids` is replaced by `teachers`.

**Params:** `search`, `page`, `per_page`

**Response**
```json
{
  "success": true,
  "message": "Classes fetched successfully",
  "data": [
    {
      "id": 1,
      "title": "Spoken English Masterclass",
      "description": "A complete course to build speaking confidence.",
      "short_description": "Speak English fluently and confidently.",
      "who_is_for": "Beginners to intermediate learners.",
      "curriculum": "Grammar basics, daily conversation, pronunciation.",
      "price": "3000.00",
      "duration_in_days": 90,
      "total_classes": 24,
      "image": "classes/spoken.jpg",
      "image_url": "http://localhost/storage/classes/spoken.jpg",
      "is_class_recording": 1,
      "teachers": [
        {
          "id": 1,
          "name": "Sarah Rahman",
          "image": null
        }
      ]
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total": 1,
    "last_page": 1
  }
}
```

## 2. `GET /api/single-class/{classId}`

Public class detail. Same new fields; `teachers` instead of `teacher_ids`.

**Params:** `classId` (path)

**Response**
```json
{
  "success": true,
  "message": "Class fetched successfully",
  "data": {
    "id": 1,
    "title": "Spoken English Masterclass",
    "description": "A complete course to build speaking confidence.",
    "short_description": "Speak English fluently and confidently.",
    "who_is_for": "Beginners to intermediate learners.",
    "curriculum": "Grammar basics, daily conversation, pronunciation.",
    "price": "3000.00",
    "duration_in_days": 90,
    "total_classes": 24,
    "image": "classes/spoken.jpg",
    "image_url": "http://localhost/storage/classes/spoken.jpg",
    "is_class_recording": 1,
    "teachers": [
      {
        "id": 1,
        "name": "Sarah Rahman",
        "image": null
      }
    ]
  }
}
```

## 3. `GET /api/teachers/{id}`

Public teacher profile. Nested `batches[].class` now includes `short_description` (plus existing class fields).

**Response (class part only)**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "name": "Aisha Khan",
    "batches": [
      {
        "id": 3,
        "name": "Morning Batch",
        "class": {
          "id": 1,
          "title": "Spoken English Masterclass",
          "description": "Full course description",
          "short_description": "Speak English fluently and confidently.",
          "price": "3000.00",
          "duration_in_days": 90,
          "total_classes": 24,
          "image": "classes/spoken.jpg",
          "image_url": "http://localhost/storage/classes/spoken.jpg"
        },
        "schedules": []
      }
    ]
  }
}
```

---

# Admin (`auth:api` + `role:admin`)

## 4. `GET /api/admin/classes`

Full class models (includes new columns). `teachers` is attached; `teacher_ids` may still appear depending on serialization before unset on some paths — list uses `withTeachers` so `teachers` is present and `teacher_ids` is removed.

**Params:** `search`, `page`, `per_page`

**Response item includes**
```json
{
  "id": 1,
  "title": "Spoken English Masterclass",
  "description": "Full description",
  "short_description": "Speak English fluently and confidently.",
  "who_is_for": "Beginners to intermediate learners.",
  "curriculum": "Grammar, conversation, pronunciation.",
  "is_class_recording": 1,
  "price": "3000.00",
  "duration_in_days": 90,
  "total_classes": 24,
  "is_active": 1,
  "image": "classes/spoken.jpg",
  "image_url": "http://localhost/storage/classes/spoken.jpg",
  "teachers": [
    { "id": 1, "name": "Sarah Rahman", "image": null }
  ]
}
```

## 5. `POST /api/admin/classes`

**Body** (`multipart/form-data` if image)
```json
{
  "title": "Spoken English Masterclass",
  "description": "Full description",
  "short_description": "Speak English fluently and confidently.",
  "who_is_for": "Beginners to intermediate learners.",
  "curriculum": "Grammar, conversation, pronunciation.",
  "teacher_ids": [1, 2],
  "is_class_recording": 1,
  "price": 3000,
  "duration_in_days": 90,
  "total_classes": 24,
  "image": "(file optional)"
}
```

**Response**
```json
{
  "success": true,
  "message": "Class created successfully",
  "data": {
    "id": 1,
    "title": "Spoken English Masterclass",
    "description": "Full description",
    "short_description": "Speak English fluently and confidently.",
    "who_is_for": "Beginners to intermediate learners.",
    "curriculum": "Grammar, conversation, pronunciation.",
    "is_class_recording": 1,
    "price": "3000.00",
    "duration_in_days": 90,
    "total_classes": 24,
    "image": "classes/spoken.jpg",
    "image_url": "http://localhost/storage/classes/spoken.jpg",
    "teachers": [
      { "id": 1, "name": "Sarah Rahman", "image": null }
    ]
  }
}
```

## 6. `GET /api/admin/classes/{id}`

Edit/show one class — same new fields + `teachers`.

## 7. `POST /api/admin/classes/{id}`

Update class — same body fields as create (`short_description`, `who_is_for`, `curriculum`, `teacher_ids`, `is_class_recording`, …).

**Response**
```json
{
  "success": true,
  "message": "Class updated successfully",
  "data": {
    "id": 1,
    "title": "Spoken English Masterclass",
    "short_description": "Updated short text",
    "who_is_for": "All levels",
    "curriculum": "Updated curriculum",
    "is_class_recording": 0,
    "teachers": []
  }
}
```

---

## Routes that did **not** change for these fields

These only select limited class columns (e.g. `id,title` / `id,title,image`) and do **not** return the new detail fields:

- `GET /api/batches/{classId}` (landing batches — class: `id,title,image`)
- Teacher/student batch lists (`class:id,title` or `id,title,image`)
- Teacher students / assignments class titles
- Student dashboard course titles

---

## Quick map

| Method | Path | Auth | New fields in response |
|--------|------|------|-------------------------|
| GET | `/api/classes` | Public | yes (+ `teachers`) |
| GET | `/api/single-class/{classId}` | Public | yes (+ `teachers`) |
| GET | `/api/teachers/{id}` | Public | `short_description` on nested class |
| GET | `/api/admin/classes` | Admin | yes |
| POST | `/api/admin/classes` | Admin | yes (body + response) |
| GET | `/api/admin/classes/{id}` | Admin | yes |
| POST | `/api/admin/classes/{id}` | Admin | yes (body + response) |
