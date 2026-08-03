# Teacher & Student Features API

Auth header for all routes: `Authorization: Bearer {token}`

**File uploads** use `multipart/form-data`.  
Allowed files: `pdf, doc, docx, jpg, jpeg, png` (max 10MB).

**Activity note statuses:** `good` | `average` | `needs_attention` | `bad`  
**Active assignment:** `due_at` is null **or** `due_at >= now`

---

# Teacher APIs

Middleware: `auth:api` + `role:teacher`

---

## A. Student tab & activity notes

### 1. `GET /api/teacher/students`

**Params:** `search`, `page`, `per_page`

**Response**
```json
{
  "success": true,
  "message": "Students fetched successfully",
  "data": [
    {
      "user_id": 12,
      "name": "Student Alpha",
      "email": "student@example.com",
      "image": null,
      "image_url": null,
      "batch_id": 3,
      "batch_name": "Morning Batch",
      "class_title": "Spoken English",
      "enrollment_status": "active",
      "enrolled_at": "2026-07-01T10:00:00.000000Z",
      "latest_note": {
        "id": 8,
        "status": "good",
        "comment": "Improving steadily",
        "created_at": "2026-08-01T12:00:00.000000Z"
      }
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

### 2. `GET /api/teacher/students/{userId}/notes`

**Params:** `userId` (path), `batch_id` (query, required), `page`, `per_page`

**Response**
```json
{
  "success": true,
  "message": "Student notes fetched successfully",
  "data": [
    {
      "id": 8,
      "batch_id": 3,
      "student_user_id": 12,
      "comment": "Needs more speaking practice",
      "status": "bad",
      "created_at": "2026-08-01T12:00:00.000000Z",
      "updated_at": "2026-08-01T12:00:00.000000Z"
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

### 3. `POST /api/teacher/student-notes`

**Body**
```json
{
  "batch_id": 3,
  "student_user_id": 12,
  "comment": "Needs more speaking practice",
  "status": "bad"
}
```

**Response**
```json
{
  "success": true,
  "message": "Student note created successfully",
  "data": {
    "id": 8,
    "batch_id": 3,
    "student_user_id": 12,
    "comment": "Needs more speaking practice",
    "status": "bad",
    "created_at": "2026-08-01T12:00:00.000000Z"
  }
}
```

### 4. `PUT /api/teacher/student-notes/{id}`

**Params:** `id` (path)

**Body**
```json
{
  "comment": "Improving steadily",
  "status": "average"
}
```

**Response**
```json
{
  "success": true,
  "message": "Student note updated successfully",
  "data": {
    "id": 8,
    "batch_id": 3,
    "student_user_id": 12,
    "comment": "Improving steadily",
    "status": "average",
    "updated_at": "2026-08-02T09:00:00.000000Z"
  }
}
```

### 5. `DELETE /api/teacher/student-notes/{id}`

**Params:** `id` (path)

**Response**
```json
{
  "success": true,
  "message": "Student note deleted successfully"
}
```

---

## B. Batch assignments & marking

### 6. `GET /api/teacher/assignments/{batchId}`

**Params:** `batchId` (path), `page`, `per_page`

**Response**
```json
{
  "success": true,
  "message": "Assignments fetched successfully",
  "data": [
    {
      "id": 1,
      "batch_id": 3,
      "teacher_id": 1,
      "title": "Essay 1",
      "description": "Write a short essay",
      "attachment_url": "http://localhost/storage/assignments/abc.pdf",
      "due_at": "2026-08-10T18:00:00.000000Z",
      "total_marks": "50.00",
      "submissions_count": 2,
      "created_at": "2026-08-02T10:00:00.000000Z",
      "updated_at": "2026-08-02T10:00:00.000000Z"
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

### 7. `POST /api/teacher/assignments`

**Body** (`multipart/form-data`)
```json
{
  "batch_id": 3,
  "title": "Essay 1",
  "description": "Write a short essay",
  "due_at": "2026-08-10 18:00:00",
  "total_marks": 50,
  "attachment": "(file optional)"
}
```

**Response**
```json
{
  "success": true,
  "message": "Assignment created successfully",
  "data": {
    "id": 1,
    "batch_id": 3,
    "teacher_id": 1,
    "title": "Essay 1",
    "description": "Write a short essay",
    "attachment_url": "http://localhost/storage/assignments/abc.pdf",
    "due_at": "2026-08-10T18:00:00.000000Z",
    "total_marks": "50.00",
    "submissions_count": null,
    "created_at": "2026-08-02T10:00:00.000000Z",
    "updated_at": "2026-08-02T10:00:00.000000Z"
  }
}
```

### 8. `GET /api/teacher/assignments-edit/{id}`

**Params:** `id` (path)

**Response** — same assignment object shape as create (includes `total_marks`, `submissions_count`)

### 9. `POST /api/teacher/assignments/{id}`

**Params:** `id` (path)

**Body** (`multipart/form-data`)
```json
{
  "title": "Essay 1 Updated",
  "description": "Updated instructions",
  "due_at": "2026-08-12 18:00:00",
  "total_marks": 60,
  "attachment": "(file optional)"
}
```

### 10. `DELETE /api/teacher/assignments/{id}`

**Params:** `id` (path)

**Response**
```json
{
  "success": true,
  "message": "Assignment deleted successfully"
}
```

### 11. `GET /api/teacher/assignments/{id}/submissions`

**Params:** `id` (path), `page`, `per_page`

**Response**
```json
{
  "success": true,
  "message": "Submissions fetched successfully",
  "data": [
    {
      "id": 10,
      "assignment_id": 1,
      "student_user_id": 12,
      "student_name": "Tanvir Ahmed",
      "student_email": "tanvir@gmail.com",
      "file_url": "http://localhost/storage/assignment-submissions/work.pdf",
      "total_marks": "50.00",
      "obtained_marks": "42.00",
      "feedback": "Strong structure",
      "graded_at": "2026-08-03T10:00:00.000000Z",
      "submitted_at": "2026-08-03T09:00:00.000000Z",
      "created_at": "2026-08-03T08:30:00.000000Z"
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

### 12. `POST /api/teacher/assignments/submissions/{submissionId}/grade`

**Params:** `submissionId` (path)

**Body**
```json
{
  "obtained_marks": 42,
  "feedback": "Strong structure"
}
```

**Response**
```json
{
  "success": true,
  "message": "Submission graded successfully",
  "data": {
    "id": 10,
    "assignment_id": 1,
    "student_user_id": 12,
    "total_marks": "50.00",
    "obtained_marks": "42.00",
    "feedback": "Strong structure",
    "graded_at": "2026-08-03T10:00:00.000000Z"
  }
}
```

`obtained_marks` must be between `0` and the assignment `total_marks`.

### Teacher batch list (active assignment count)

`GET /api/teacher/batches` and `GET /api/teacher/single-batch/{batchId}` include:

```json
{
  "id": 3,
  "name": "Morning Batch",
  "active_assignments_count": 2
}
```

---

# Student APIs

Middleware: `auth:api` + `role:student`

---

## C. Activity notes (from teachers)

### 13. `GET /api/student/activity-notes`

**Params:** `page`, `per_page`

**Response**
```json
{
  "success": true,
  "message": "Activity notes fetched successfully",
  "data": [
    {
      "id": 8,
      "batch_id": 3,
      "batch_name": "Morning Batch",
      "teacher_id": 1,
      "teacher_name": "Sarah Rahman",
      "comment": "Participates well in class",
      "status": "good",
      "created_at": "2026-08-01T12:00:00.000000Z",
      "updated_at": "2026-08-01T12:00:00.000000Z"
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

---

## D. Assignments & marks

### 14. `GET /api/student/assignments` (Assignment tab)

**Params:** `search`, `pending_only=1`, `page`, `per_page`

**Response**
```json
{
  "success": true,
  "message": "Active assignments fetched successfully",
  "data": [
    {
      "id": 1,
      "batch_id": 3,
      "teacher_id": 1,
      "title": "Essay 1",
      "description": "Write a short essay",
      "attachment_url": "http://localhost/storage/assignments/abc.pdf",
      "due_at": "2026-08-10T18:00:00.000000Z",
      "total_marks": "50.00",
      "submissions_count": null,
      "created_at": "2026-08-02T10:00:00.000000Z",
      "updated_at": "2026-08-02T10:00:00.000000Z",
      "batch_name": "Morning Batch",
      "class_title": "Spoken English Masterclass",
      "is_open": true,
      "has_submitted": true,
      "my_submission": {
        "id": 10,
        "file_url": "http://localhost/storage/assignment-submissions/work.pdf",
        "obtained_marks": "42.00",
        "feedback": "Strong structure",
        "graded_at": "2026-08-03T10:00:00.000000Z",
        "submitted_at": "2026-08-03T09:00:00.000000Z"
      }
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

### 15. `GET /api/student/assignments/{batchId}`

**Params:** `batchId` (path), `page`, `per_page`

Same mark fields: `total_marks` + `my_submission` (`obtained_marks`, `feedback`, `graded_at`).

### 16. `POST /api/student/assignments/{assignmentId}/submit`

**Params:** `assignmentId` (path)

**Body** (`multipart/form-data`)
```json
{
  "file": "(file required)"
}
```

**Response**
```json
{
  "success": true,
  "message": "Assignment submitted successfully",
  "data": {
    "id": 10,
    "assignment_id": 1,
    "student_user_id": 12,
    "file_url": "http://localhost/storage/assignment-submissions/work.pdf",
    "total_marks": "50.00",
    "obtained_marks": null,
    "feedback": null,
    "graded_at": null,
    "submitted_at": "2026-08-03T09:00:00.000000Z"
  }
}
```

Replacing a file clears previous marks until the teacher grades again.

### Student batch list (active assignment count)

`GET /api/student/batches` and `GET /api/student/single-batch/{batchId}` include:

```json
{
  "id": 3,
  "name": "Morning Batch",
  "active_assignments_count": 2
}
```

---

## E. Student dashboard

### 17. `GET /api/student/dashboard`

Extra fields (existing stats kept):

```json
{
  "success": true,
  "message": "Student dashboard retrieved successfully",
  "data": {
    "statistics": {
      "total_enrollments": 2,
      "active_courses": 1,
      "completed_courses": 0,
      "total_spent": 3000,
      "pending_assignments": 1
    },
    "recent_graded_assignments": [
      {
        "submission_id": 10,
        "assignment_id": 1,
        "title": "Essay 1",
        "batch_name": "Morning Batch",
        "obtained_marks": "42.00",
        "total_marks": "50.00",
        "feedback": "Strong structure",
        "graded_at": "2026-08-03T10:00:00.000000Z"
      }
    ],
    "recent_activity_notes": [
      {
        "id": 8,
        "status": "good",
        "comment": "Participates well in class",
        "batch_name": "Morning Batch",
        "teacher_name": "Sarah Rahman",
        "created_at": "2026-08-01T12:00:00.000000Z"
      }
    ]
  }
}
```

---

## Quick route map

### Teacher
| Method | Path |
|--------|------|
| GET | `/api/teacher/students` |
| GET | `/api/teacher/students/{userId}/notes` |
| POST | `/api/teacher/student-notes` |
| PUT | `/api/teacher/student-notes/{id}` |
| DELETE | `/api/teacher/student-notes/{id}` |
| GET | `/api/teacher/assignments/{batchId}` |
| POST | `/api/teacher/assignments` |
| GET | `/api/teacher/assignments-edit/{id}` |
| POST | `/api/teacher/assignments/{id}` |
| DELETE | `/api/teacher/assignments/{id}` |
| GET | `/api/teacher/assignments/{id}/submissions` |
| POST | `/api/teacher/assignments/submissions/{submissionId}/grade` |

### Student
| Method | Path |
|--------|------|
| GET | `/api/student/activity-notes` |
| GET | `/api/student/assignments` |
| GET | `/api/student/assignments/{batchId}` |
| POST | `/api/student/assignments/{assignmentId}/submit` |
| GET | `/api/student/dashboard` |
