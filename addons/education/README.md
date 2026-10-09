# Education & Past Questions addon

This addon contains two separate catalogue sections:

- **School Past Questions** (school_past_question): institution, faculty, department, course code/title, level, semester and academic session.
- **Exam Past Questions** (exam_past_question): examination body, exam type, subject and year.

## Capabilities

- Admin/staff can upload PDF, DOC and DOCX files (20 MB maximum), manage metadata, publish or archive resources, and set free or NGN-priced access.
- Uploads are stored on Laravel's private local disk, not in the public web directory. Files are served only through an authenticated controller after access checks.
- Admins may attach an optional PDF or image preview. Previews are stored privately and served inline only for published resources; the full file still requires free access or a successful purchase.
- Free resources can be downloaded by authenticated users. Paid resources require a successful purchase record.
- Paid purchases debit an active same-currency wallet inside a database transaction and write a wallet movement with a stable operation key. The purchase is idempotent per user/resource.
- Resources with purchase history cannot be deleted; archive them to preserve customer access and audit history.
- Search and metadata filters are available on the user catalogues; admin lists support category/status/search filtering.

## Routes

- /education/school-past-questions
- /education/exam-past-questions
- /admin/education/past-questions

The addon requires Core's normal addon lifecycle, permissions, wallet tables and transaction PIN middleware. Do not enable paid content until the addon migrations have run and wallet purchase smoke tests have passed on a non-production database.

Provider-powered education product purchases remain handled by the existing Education provider transaction services and are separate from downloadable past-question resources.
