# Education & Past Questions addon

This addon contains two separate catalogue sections:

- **School Past Questions** (school_past_question): institution, faculty, department, course code/title, level, semester and academic session.
- **Exam Past Questions** (exam_past_question): examination body, exam type, subject and year.

## Imported reference catalogues

The reference-data migration imports a starting directory of **28+ examination organisations/programmes** and **70+ Nigerian tertiary/specialised institutions**, grouped by institution type (federal/state/private universities, polytechnics, colleges of education, health/nursing colleges and specialised institutions). It includes exam-body aliases and exam types such as WAEC/WASSCE, NECO/SSCE, JAMB/UTME, NABTEB, NBAIS, NCEE, JUPEB, IJMB, state BECE/placement examinations, Cambridge, Edexcel, SAT, TOEFL, GRE, GMAT, IELTS and selected professional qualifying examinations.

The catalogue is reference metadata, not a claim that every listed school has downloadable past-question files already. It supplies filter options and admin autocomplete; actual papers must be uploaded/licensed separately. Schools and exam bodies can be extended from the same reference data file. Regulator/source directories used include [NUC](https://www.nuc.edu.ng/approved-affiliations/), [NBTE](https://web.nbte.gov.ng/tvet%20institutions), [NCCE](https://www.ncce.gov.ng/AccreditedColleges), [Federal Ministry of Education](https://education.gov.ng/government-polytechnics/), [JUPEB](https://jupeb.edu.ng/) and [Lagos State Examinations Board](https://examsboard.lagosstate.gov.ng/).

## Admin-managed reference catalogue

Administrators can open **Admin → Education Reference Catalogue** to add schools/institutions, examination bodies, and exam types after installation. They can create reusable categories, search/filter the reference list, attach official/source URLs and locations, and remove obsolete references (school records already used by library resources are protected). The catalogue is database-backed and is not limited to the entries shipped in the initial seed file. Added references are available to the past-question catalogue filters and admin upload suggestions; adding a reference does not automatically create a past-question document.

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
