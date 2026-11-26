# Laravel Point of Sale Implementation Plan

*IT0049 Midterm Project*

Prepared 13 November 2026

Build one deployable Laravel application that meets the attached POS brief: manage products, customers and staff, record sales, update inventory correctly, and display transaction history. This plan defines the build sequence, data model, routes, security controls, tests and submission evidence.

Framework adaptation: the source assignment explicitly requests CodeIgniter. The user confirmed on 16 November 2026 that Laravel is approved for this submission. GitHub creation/publication is excluded from this task. Hosting remains a separate, unverified delivery requirement.

## Technology choices

| Area | Implementation |
| --- | --- |
| Application | Laravel 13 with PHP 8.3 or higher and Composer; commit composer.lock. Laravel 13 requires PHP 8.3 or higher [1]. |
| Interface | Laravel Blade, HTML forms, plain CSS and small amounts of vanilla JavaScript. Use server rendering for every required screen. |
| Database | MySQL with InnoDB for both development and hosting; use Laravel migrations, seeders and Eloquent. |
| Authentication | Laravel session guard, Auth, Hash and authentication middleware; username login with no public registration. |
| Files and testing | Laravel Storage and validation; native PHP GD for image preparation; PHPUnit through php artisan test. |

## Required delivery scope

- Product, customer and staff management with create, list, view, edit and remove actions. Products and staff include prepared images.

- A protected Record Sale form and a Sales History page showing all fields required by the brief.

- A GitHub repository with migrations and seeders, a reproducible README, and a working hosted application.

Current scope: the required Record Sale page uses one product per sale, an optional customer and one configured currency with two decimal places. A separate Checkout page adds carts, payment recording and receipts. Admin/cashier roles, stock history, snapshots and duplicate protection were added as extensions. Tax calculations and refunds remain outside the required build. The remaining sections describe the original baseline implementation plan; `docs/SETUP.md` and `docs/validation.md` describe the current implementation and verification.

## Requirements and Laravel structure

Use a single MVC application. Routes select a controller, a Form Request validates input, Eloquent handles persistence, and Blade renders the response. Keep stock and image operations in small application services so controllers remain easy to review.

| Requirement | Laravel implementation | Completion evidence |
| --- | --- | --- |
| Products | ProductController, Product model and product views | CRUD works; prepared image displays; stock is nonnegative. |
| Customers | CustomerController and Customer model | Full CRUD; customer may be omitted from a sale. |
| Staff | StaffController and authenticatable User model | Full CRUD; avatar displays; passwords are hashed. |
| Login protection | SessionController and auth middleware | Guests cannot open any management or sales route. |
| Record Sale | SaleController and RecordSale service | Successful sale creates a row and reduces stock once. |
| Overselling prevention | Transaction and product row lock | Insufficient stock rejects the sale without any write. |
| History | Sale index with eager loaded relations | Product, customer, staff, quantity, total and date display. |
| Submission | Migrations, seeders, README and deployment | Fresh setup succeeds; hosted code matches the repository. |

### File responsibilities

- routes/web.php defines named web routes. app/Http/Controllers contains SessionController, ProductController, CustomerController, StaffController and SaleController.

- app/Http/Requests contains Store and Update requests for each managed entity, LoginRequest and StoreSaleRequest.

- app/Models contains Product, Customer, User and Sale. app/Services contains RecordSale and PrepareImage.

- resources/views contains layouts, shared form components, auth, products, customers, staff and sales views. Keep CSS in public/css/app.css to avoid requiring a JavaScript build.

- database/migrations and database/seeders reproduce the schema and demo data. tests/Feature contains authentication, CRUD, upload and sales tests.

Reuse Blade components for field errors, image placeholders, confirmation forms, success messages and pagination. Return redirects after successful POST requests to avoid resubmission on refresh.

## Database design and record lifecycle

Preserve the four supplied business tables and all three sales foreign keys. Use auto-incrementing INT primary keys with matching foreign key types. Columns are nonnullable unless marked nullable. Migrate users, products and customers before sales. Document schema additions in the README.

| Table | Required columns |
| --- | --- |
| products | id INT primary key; name VARCHAR(100); price DECIMAL(10,2); stock_quantity INT default 0; image VARCHAR(255) nullable; created_at DATETIME. |
| customers | id INT primary key; full_name VARCHAR(100); email VARCHAR(100); phone VARCHAR(20) nullable; created_at DATETIME. |
| users | id INT primary key; username VARCHAR(50) unique; full_name VARCHAR(100); password VARCHAR(255); avatar VARCHAR(255) nullable; created_at DATETIME. |
| sales | id INT primary key; product_id INT; customer_id INT nullable; sold_by INT; quantity INT; total_price DECIMAL(10,2); created_at DATETIME. |

### Laravel additions and model configuration

Add updated_at to each business table and nullable deleted_at to products, customers and users. Use Laravel SoftDeletes for their remove actions. Add nullable remember_token to users for compatibility with Laravel session authentication. Replace the default user name and email fields with username and full_name; email login, verification and password reset are not in scope.

Set created_at to a nonnullable DATETIME with an appropriate default. Use decimal:2 casts for product price and sale total, and integer casts for stock and quantity. Hide password and remember_token from serialization. Use Laravel file sessions and file cache for this small deployment; additional business tables are unnecessary.

### Relationships and deletion rules

- Sale belongsTo Product through product_id, Customer through customer_id, and User as staff through sold_by. Each parent hasMany sales.

- Foreign keys restrict physical deletion of referenced parents. Never cascade-delete sales. Soft deletion hides a parent from active management lists and sale selections while retaining the record.

- Use withTrashed on the history relationships so removed products, customers and staff still display. A null customer displays as Walk in customer.

- Prevent a staff member from removing their own account. A removed user cannot log in or continue a protected session. Usernames remain reserved after removal.

Index the sales foreign keys and created_at. Customer email is required and validated, but uniqueness is not imposed because the brief does not require it. Sale totals are stored at the time of sale and never recalculated from the current product price. Historical labels use the related records; immutable name snapshots would be an optional schema extension.

## Routes and authentication

Place all management and sales routes in the web middleware group with auth and auth.session. Only the login form and login submission are accessible to guests. Use named routes, route model binding, CSRF protection and Laravel method spoofing for PATCH and DELETE forms.

| Method | Path | Action | Access |
| --- | --- | --- | --- |
| GET | /login | Login form | Guest |
| POST | /login | Authenticate username and password | Guest and throttled |
| POST | /logout | End the current session | Authenticated |
| GET | / | Redirect to products | Authenticated |
| Resource | /products | ProductController CRUD | Authenticated |
| Resource | /customers | CustomerController CRUD | Authenticated |
| Resource | /staff | StaffController CRUD using {user} | Authenticated |
| GET | /sales/create | Record Sale form | Authenticated |
| POST | /sales | Store sale | Authenticated |
| GET | /sales | Sales History | Authenticated |

For each resource, provide GET index, create, show and edit; POST store; PATCH update; and DELETE destroy. Configure the staff resource parameter as user so it binds to App\Models\User. Sales expose only index, create and store; submitted transactions have no edit or delete screen.

### Login and staff provisioning

1. Create the first staff account through a seeder that reads a password from the environment. Keep production credentials out of source control and rotate temporary demo credentials.

2. Validate username and password, then call Auth::attempt with those credentials. Normalize usernames consistently during creation and login. Apply a limiter keyed by normalized username and IP.

3. On success regenerate the session and redirect to the intended protected URL. On failure show a generic credentials message and retain only the username [2].

4. On logout call Auth::logout, invalidate the session and regenerate the CSRF token. Configure secure cookies and HTTPS on the host [2].

Use Hash::make when creating staff or changing a password. A blank password on edit leaves the hash unchanged. Apply auth.session so password changes invalidate other sessions when they next access protected pages. No public registration or email-based starter kit is needed.

## Management screens and image preparation

### Product management

List products with name, price, available stock and thumbnail, with pagination and create, view, edit and remove actions. Forms collect name, price, stock quantity and an optional image. Display field errors and preserve valid input. Removing a product archives it and removes it from sale selection.

Treat stock edits as an explicit inventory operation: load the product inside a transaction with lockForUpdate, and update only validated fields. Include the stock value originally shown in the edit form; if submitted stock differs from that baseline and current stock has changed, reject the stale adjustment. Ordinary edits that do not change stock must not overwrite it.

### Customer and staff management

Customer forms collect full name, email and optional phone. Staff forms collect unique username, full name, password confirmation and optional avatar. Staff edits retain the current password and image unless a replacement is supplied. Use soft deletion for both entities, with a confirmation screen and a clear success message.

Use shared page layouts with navigation for Products, Customers, Staff, Record Sale and Sales History. Show the current staff member and a POST logout form. Every input has a label; errors appear beside the relevant field; tables remain usable on narrow screens.

### Display ready uploads

1. Validate actual image content, size and dimensions. Accept JPEG, PNG and WebP only, up to 2 MB and 4096 by 4096 pixels. Reject SVG, executable content, corrupt files and unsupported formats [4].

2. Prepare images with native PHP GD inside PrepareImage. Resize product images to fit within 1200 by 1200 without enlarging them. Crop avatars to 256 by 256. Reencode into a known JPEG or PNG output and discard the original upload.

3. Generate a random server filename. Store prepared outputs through Laravel Storage under products or avatars on the public disk. Save only the relative path in image or avatar, and use a placeholder when no image exists.

4. Save the new file before updating its database reference. If the database write fails, delete the new file. Delete the previous file only after commit, and log cleanup failures. Keep images for soft-deleted records.

5. Create the public storage link and verify URLs on the host. Permit public viewing of prepared images; restrict upload and replacement to authenticated staff [5].

Hosting prerequisite: enable PHP GD with support for the accepted formats. Enforce the same upload cap in PHP and the web server. The application uses no third party image package.

## Sale recording and inventory consistency

The Record Sale form lists active products with current price and available stock, an optional active customer, and a positive integer quantity. Display an estimated total for convenience. The server always uses the database price and the authenticated staff ID.

### Transaction workflow

1. StoreSaleRequest validates product_id, nullable customer_id and quantity. Existence checks exclude soft-deleted records. Ignore any submitted total_price or sold_by.

2. RecordSale starts DB::transaction. Reload the product with lockForUpdate and verify that it is active. If a customer is supplied, reload and lock that active customer as well so concurrent removal cannot bypass the check.

3. Compare quantity with the stock read under the lock. Reject an excessive request with Available stock is X units. Do not insert a sale or change inventory.

4. Convert the database price string to integer cents without floating point arithmetic. For a positive price, reject quantities above intdiv(9999999999, unitPriceCents) before multiplication. A zero price produces a zero total.

5. Create the sale with product_id, nullable customer_id, sold_by from the session, quantity, exact total_price and the current timestamp.

6. Subtract quantity from the locked product and save it. Commit both writes together. Any exception rolls back both operations. A bounded deadlock retry may rerun the transaction [3].

7. Redirect to Sales History with a success message. On rejection, retain the form input and show the relevant validation error.

The cent limit above corresponds to the source DECIMAL(10,2) maximum of 99,999,999.99. Use a 64 bit PHP runtime and format integer cents as a decimal string for persistence. Never cast money to a float.

### Expected results

| Starting state | Action | Result |
| --- | --- | --- |
| Stock 10; price 125.50 | Sell 3 units | Stock 7; one sale; total 376.50. |
| Stock 2 | Try to sell 3 units | Clear error; stock 2; no new sale. |
| Stock 5; two staff sessions | Both try to sell 4 units | One sale succeeds; one fails; final stock 1. |
| Any valid product | Force the sale insert to fail | No sale and no stock change after rollback. |

All stock writes use the same row locking discipline, including product edits. Disable repeat clicks while submitting and redirect after success. These are usability protections; strong protection against replayed POST requests would require an optional unique submission token.

## Validation and security rules

Put validation in Form Requests and persist only validated, explicitly allowed fields. Configure authorization for authenticated staff. These project rules preserve the source limits and add practical checks for a working application [4].

| Input | Rules |
| --- | --- |
| Product name | Required string, maximum 100 characters; trim surrounding whitespace. |
| Price | Required decimal with up to two places, from 0 through 99999999.99. Reject values that would be silently rounded. |
| Stock quantity | Required integer from 0 through 2147483647; apply the stale stock adjustment check during edits. |
| Customer | Full name required, maximum 100; email required, valid and maximum 100; phone nullable string, maximum 20. |
| Staff | Username required, maximum 50, normalized and unique including removed accounts; full name required, maximum 100. |
| Password | Required on create; optional on edit; confirmed; minimum 8 characters. With bcrypt, enforce at most 72 bytes before hashing. |
| Sale | Active product required; active customer nullable; quantity integer from 1 through 2147483647; recheck stock and total limits inside the transaction. |
| Image or avatar | Optional image; JPEG, PNG or WebP; maximum 2 MB; maximum dimensions 4096 by 4096; successful decode required. |

### Application controls

- Protect every management request, including store, update, destroy and sales history. A removed user must be rejected by the authentication provider on the next request.

- Include @csrf in every state-changing form and @method where required. Keep user input escaped with normal Blade output. Never render a supplied name as raw HTML.

- Use Eloquent or bound query parameters. Define fillable fields carefully; stock, sale total and sold_by receive explicit server handling.

- Use HTTPS, secure and HttpOnly session cookies, and APP_DEBUG=false in production. Keep .env, application keys and database credentials out of Git.

- Handle missing and removed records with a clear 404 or validation response. Log unexpected failures without exposing stack traces, credentials or passwords to the user.

### History behavior

Paginate sales newest first and eager load product, customer and staff, including soft-deleted parents. Display product name, customer or Walk in customer, staff full name, quantity, stored total and sale date. Display the configured currency consistently and convert stored UTC timestamps to the application display timezone.

## Acceptance tests and review evidence

Run feature tests with PHPUnit through php artisan test. Use a dedicated MySQL test database so the engine matches deployment. Test database writes, authentication boundaries and upload behavior; use manual review for layout and image appearance.

| ID | Scenario | Passing result |
| --- | --- | --- |
| T01 | Open management URLs as a guest | All GET and mutation routes require login; no data changes. |
| T02 | Log in, log out and retry bad credentials | Valid login works; logout ends access; bad attempts are limited. |
| T03 | Create, view, edit and remove a product | Fields persist, image displays, removed product is not selectable. |
| T04 | Complete customer and staff CRUD | Changes persist; passwords are hashed; avatars display; self-removal fails. |
| T05 | Submit invalid forms and uploads | Field errors; no negative stock; bad email, corrupt image and oversized file rejected. |
| T06 | Sell 3 units at 125.50 from stock 10 | One sale for 376.50; final stock 7; correct staff and customer. |
| T07 | Sell all stock, then sell again | First request leaves zero; next request fails with no additional sale. |
| T08 | Request 3 units from stock 2 | No sale inserted; stock unchanged; clear insufficient stock error. |
| T09 | Omit customer and tamper with total or seller | Walk in sale works; database price and logged-in staff are used. |
| T10 | Force a failure between sale and stock writes | Transaction rolls back both changes. |
| T11 | Run simultaneous sales for scarce stock | Independent MySQL connections yield no oversell and no negative stock. |
| T12 | Change price and remove related records | Prior total stays unchanged; history still shows all related names. |
| T13 | Submit stale product stock and an excessive total | Stock conflict or total limit is rejected without partial writes. |
| T14 | Replace an image and remove a staff account | Old file removed after commit; removed user loses access on next request. |

For T11, use two independently authenticated sessions or an integration test with separate database connections and overlapping requests. A sequential test or SQLite-only test does not establish row lock correctness. For T05, test CSRF rejection through a browser or with CSRF middleware explicitly enabled, since normal Laravel tests may bypass it.

Before submission, repeat the main workflow on the hosted application: log in, add product and customer, add staff with avatar, record a valid sale, reject an excessive sale, inspect history and log out. Verify images after a redeployment and confirm that stock and history survive.

## Build sequence and grading evidence

Estimate 10 focused working days for one developer, roughly 50 to 60 hours. This is a planning estimate, not a course deadline. Complete each exit condition before starting dependent work. Establish a minimal hosted deployment early to expose PHP, storage and database constraints.

| Day | Implementation work | Exit condition |
| --- | --- | --- |
| 1 | Create Laravel project, repository, environment and minimal host deployment. | Login page or initial route loads locally and on the host. |
| 2 | Implement migrations, relationships, factories and safe seeders. | Fresh database setup reproduces all four tables and foreign keys. |
| 3 | Implement username login, logout, middleware and shared Blade layout. | Guest access blocked; seeded staff can log in and out. |
| 4 | Build product CRUD and PrepareImage; add stock edit handling. | Product operations and prepared images work. |
| 5 | Build customer CRUD and reuse forms and pagination. | All customer operations and validation work. |
| 6 | Build staff CRUD, avatar handling and password updates. | Hashed passwords, unique usernames and removal rules work. |
| 7 | Build RecordSale, locking, exact totals and error messages. | Valid, insufficient stock and rollback scenarios pass. |
| 8 | Build history and complete concurrency and security tests. | History and removal behavior work; no overselling. |
| 9 | Finish hosted configuration, storage and responsive review. | Hosted acceptance run passes from a clean browser session. |
| 10 | Finish README, screenshots, clean install check and submission links. | Repository and live app match the submitted release. |

### Evidence for the supplied rubric

| Criterion | Points | Evidence to retain |
| --- | --- | --- |
| Architecture and design | 20 | Schema diagram, route list, model relationships and this implementation plan. |
| Feature completeness | 30 | Working CRUD screens, successful sale, stock reduction and rejection example. |
| Validation and security | 20 | Form errors, safe prepared uploads, login protection and relevant test results. |
| Code quality | 15 | Small controllers, shared Blade forms, Form Requests and focused services. |
| Documentation and deployment | 15 | Reproducible README, migrations and seeders, GitHub URL and live URL. |

## Deployment and submission checklist

### Local setup and release commands

The README should give these commands for a fresh development clone, after installing the required PHP extensions and configuring MySQL. Run migrations against a dedicated development database.

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configure DB values and the initial staff secret in .env
php artisan migrate --seed
php artisan storage:link
php artisan test
php artisan serve
```

### Hosted release

- Choose a PHP host with the Laravel 13 runtime requirements, MySQL InnoDB, PHP GD, HTTPS and persistent writable storage. Configure the document root as public/ [6].

- Deploy the submitted Git commit. Run composer install --no-dev --optimize-autoloader. Set APP_ENV=production, APP_DEBUG=false, APP_URL, database settings and secure session cookies. Generate APP_KEY once and preserve it.

- Back up the database before later schema changes. Run php artisan migrate --force and php artisan storage:link, then cache configuration, routes and views. Bootstrap the first staff member explicitly; do not reseed demo data on every release.

- Grant the application write access to storage and bootstrap/cache. Keep prepared images on persistent storage and confirm that their URLs work after redeployment.

- Complete the hosted acceptance run, check application logs, and retain the previous release plus database and upload backups for recovery.

### README and submission contents

Include prerequisites, exact installation commands, environment variable names, database setup, initial staff provisioning, image preparation rules, route summary, framework adaptation, schema additions, test commands and known scope limits. Add screenshots of CRUD, Record Sale, rejection and history. Submit the GitHub repository URL and the hosted application URL, with authorized grader access provided through the course submission channel.

### Source material and Laravel references

Requirements source: IT0049 Midterm Project The Complete Point of Sale System, supplied DOCX. The framework substitution and implementation choices in this plan are recommendations for carrying out the user request.

[1] Laravel 13 release notes — https://laravel.com/docs/13.x/releases

[2] Authentication — https://laravel.com/docs/13.x/authentication

[3] Query builder and locking — https://laravel.com/docs/13.x/queries

[4] Validation — https://laravel.com/docs/13.x/validation

[5] File storage — https://laravel.com/docs/13.x/filesystem

[6] Deployment — https://laravel.com/docs/13.x/deployment

Documentation checked 13 November 2026. Confirm the host PHP version before implementation and pin the installed Laravel dependencies in composer.lock.
