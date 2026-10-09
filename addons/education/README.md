# Education & Past Questions addon

This addon contains two separate catalogue sections:

- **School Past Questions** (school_past_question): institution, faculty, department, course code/title, level, semester and academic session.
- **Exam Past Questions** (exam_past_question): examination body, exam type, subject and year.

## Imported reference catalogues

The reference-data migrations import **30+ examination organisations/programmes** and a growing Nigerian institution directory. The incremental official-directory expansion adds **97 curated school references** drawn from the NUC university system directory and the NCCE accredited-college directory, alongside the existing school seed list. Entries are grouped by institution type (federal/state/private universities, polytechnics, colleges of education, health/nursing colleges and specialised institutions). It includes exam-body aliases and exam types such as WAEC/WASSCE, NECO/SSCE, JAMB/UTME, NABTEB, NBAIS, NCEE, JUPEB, IJMB, state BECE/placement examinations, Cambridge, Edexcel, SAT, TOEFL, GRE, GMAT, IELTS and selected professional qualifying examinations.

The catalogue is reference metadata, not a claim that every listed school has downloadable past-question files already. The expansion seed is maintained at `addons/education/data/official-directory-expansion.php` and imported by migration `2026_10_09_110300_expand_official_school_reference_catalogue.php`; that additive migration skips entries already present and corrects old NUC/NBTE/NCCE source links. It supplies filter options and admin autocomplete; actual papers must be uploaded/licensed separately. Schools and exam bodies can be extended from the same reference data file. Regulator/source directories used include the [NUC Nigerian University System](https://enuc.nuc.edu.ng/nus), [NBTE approved institutions](https://web.nbte.gov.ng/tvet%20institutions), [NCCE accredited colleges](https://ncce.gov.ng/AccreditedColleges), [JUPEB](https://jupeb.edu.ng/) and [Lagos State Examinations Board](https://examsboard.lagosstate.gov.ng/). The imported list is an initial curated expansion, **not yet a complete mirror** of every institution on every regulator's live directory. The NUC directory currently reports 77 federal, 69 state and 182 private universities (328 total); use the CSV import to complete and periodically refresh the catalogue against the live directories.

## Bulk reference import

The Admin → Education Reference Catalogue page accepts CSV uploads (maximum 5 MB and 2,000 data rows per import). Start with `addons/education/data/reference-catalogue-template.csv`. Required headers are `kind,name,category`; optional headers are `short_name,state,country,official_url,source_url`. Supported kinds are `school`, `exam_body`, and `exam_type`. School rows require a category such as `federal_university`, `state_university`, `private_university`, `federal_polytechnic`, `state_polytechnic`, `private_polytechnic`, `college_of_education`, or another admin-defined category. Duplicate references are skipped and invalid rows are counted; imported entries are activated and attributed to the admin who imported them.

Use official regulator directories when preparing a national catalogue: [NUC Nigerian University System](https://enuc.nuc.edu.ng/nus) currently lists 77 federal, 69 state, and 182 private universities; [NBTE approved institutions](https://web.nbte.gov.ng/tvet%20institutions) lists technical and vocational institution groups; [NCCE accredited colleges](https://ncce.gov.ng/AccreditedColleges) lists accredited colleges of education. These are external reference sources, not included question-paper files.

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
