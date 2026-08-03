# Class Details API (updated responses)

From migration `2026_08_01_000000_add_class_details_to_classes_table`.

**Columns on `classes`:**
- `short_description` (text, nullable)
- `who_is_for` (text, nullable)
- `curriculum` (text, nullable)
- `is_class_recording` (0|1, default 0)

Teachers are **not** stored on the class. Assign teachers on **batches** (`batches.teacher_id`). Class list/detail responses derive:

- `teachers_count` — distinct teachers assigned via this class’s batches
- `batches_count` — batches for this class that have a teacher
- `teachers[]` — each teacher with `batches_count` and their `batches` under this class

---

# Public (no auth)

## 1. `GET /api/classes`

Landing class list. Includes detail fields plus teachers/batches derived from batch assignments.

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
      "teachers_count": 2,
      "batches_count": 3,
      "teachers": [
        {
          "id": 1,
          "name": "Sarah Rahman",
          "image": null,
          "batches_count": 2,
          "batches": [
            {
              "id": 1,
              "name": "Morning Batch",
              "status": "upcoming",
              "active_status": 1
            },
            {
              "id": 2,
              "name": "Evening Batch",
              "status": "upcoming",
              "active_status": 1
            }
          ]
        },
        {
          "id": 2,
          "name": "Aisha Khan",
          "image": null,
          "batches_count": 1,
          "batches": [
            {
              "id": 3,
              "name": "Weekend Batch",
              "status": "upcoming",
              "active_status": 1
            }
          ]
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

Public class detail. Same teacher/batch summary as the list.

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
    "teachers_count": 2,
    "batches_count": 3,
    "teachers": [
      {
        "id": 1,
        "name": "Sarah Rahman",
        "image": null,
        "batches_count": 2,
        "batches": [
          {
            "id": 1,
            "name": "Morning Batch",
            "status": "upcoming",
            "active_status": 1
          }
        ]
      }
    ]
  }
}
```

## 3. `GET /api/class-teachers/{classId}`

Teachers for a class, grouped with the batches they teach under that class (same teacher objects as above).

**Response**
```json
{
  "success": true,
  "message": "Class teachers retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Sarah Rahman",
      "image": null,
      "batches_count": 2,
      "batches": [
        {
          "id": 1,
          "name": "Morning Batch",
          "status": "upcoming",
          "active_status": 1
        }
      ]
    }
  ]
}
```

## 4. `GET /api/teachers/{id}`

Public teacher profile. Nested `batches[].class` includes `short_description` (plus existing class fields). Teachers appear here via **batch** assignment, not a class `teacher_ids` field.

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

## 5. `GET /api/admin/classes`

Full class models plus derived `teachers_count`, `batches_count`, and `teachers` (from batches).

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
  "teachers_count": 1,
  "batches_count": 1,
  "teachers": [
    {
      "id": 1,
      "name": "Sarah Rahman",
      "image": null,
      "batches_count": 1,
      "batches": [
        {
          "id": 1,
          "name": "Morning Batch",
          "status": "ongoing",
          "active_status": 1
        }
      ]
    }
  ]
}
```

## 6. `POST /api/admin/classes`

Do **not** send `teacher_ids`. Assign teachers when creating/updating batches.

**Body** (`multipart/form-data` if image)
```json
{
  "title": "Spoken English Masterclass",
  "description": "Full description",
  "short_description": "Speak English fluently and confidently.",
  "who_is_for": "Beginners to intermediate learners.",
  "curriculum": "Grammar, conversation, pronunciation.",
  "is_class_recording": 1,
  "price": 3000,
  "duration_in_days": 90,
  "total_classes": 24,
  "image": "(file optional)"
}
```

**Response** — newly created classes have empty teachers until batches with `teacher_id` exist:
```json
{
  "success": true,
  "message": "Class created successfully",
  "data": {
    "id": 1,
    "title": "Spoken English Masterclass",
    "teachers_count": 0,
    "batches_count": 0,
    "teachers": []
  }
}
```

## 7. `GET /api/admin/classes/{id}`

Edit/show one class — detail fields + derived teachers/batches summary.

## 8. `POST /api/admin/classes/{id}`

Update class — same body fields as create (`short_description`, `who_is_for`, `curriculum`, `is_class_recording`, …). No `teacher_ids`.

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
    "teachers_count": 0,
    "batches_count": 0,
    "teachers": []
  }
}
```

---

## Assigning teachers

Use batch APIs (`teacher_id` on create/update batch). Class endpoints only **read** that relationship for display.

---

## Routes that did **not** change for these fields

These only select limited class columns (e.g. `id,title` / `id,title,image`) and do **not** return the new detail fields or teacher summary:

- `GET /api/batches/{classId}` (landing batches — class: `id,title,image`)
- Teacher/student batch lists (`class:id,title` or `id,title,image`)
- Teacher students / assignments class titles
- Student dashboard course titles

---

## Quick map

| Method | Path | Auth | Detail fields | Teachers from batches |
|--------|------|------|---------------|------------------------|
| GET | `/api/classes` | Public | yes | yes |
| GET | `/api/single-class/{classId}` | Public | yes | yes |
| GET | `/api/class-teachers/{classId}` | Public | n/a | yes |
| GET | `/api/teachers/{id}` | Public | `short_description` on nested class | via batches |
| GET | `/api/admin/classes` | Admin | yes | yes |
| POST | `/api/admin/classes` | Admin | yes (body + response) | empty until batches |
| GET | `/api/admin/classes/{id}` | Admin | yes | yes |
| POST | `/api/admin/classes/{id}` | Admin | yes (body + response) | yes |
