# Campus Library

Campus Library lets students and teachers browse the school's books and reserve them online, while the librarian approves requests and keeps the catalog up to date.

Built with PHP, SQLite (through PDO) and one stylesheet (`assets/css/style.css`).

## Who uses it

**Visitors**
- Browse the full catalog without an account.
- See each book's cover, author, category and how many copies are available.
- Create an account as a student or a teacher.

**Students and teachers**
- Log in to a personal dashboard with reservation stats.
- Reserve any book that has copies available.
- Track every reservation with its dates and status: Pending, Approved, Rejected or Returned.
- Renew a loan up to 2 times, 7 more days each, as long as it is not overdue.
- See overdue loans flagged in red.

**Librarians**
- Approve or reject reservation requests from one list.
- Mark returned books so the copy goes back on the shelf, and spot overdue loans.
- Add, edit and delete books, including cover images.
- Add new categories and see how many books each holds.
- View all users and make someone a librarian.

## How a reservation works

1. **Find a book.** Use the catalog on your dashboard. Books with no copies left show as out of stock.
2. **Reserve it.** Select Reserve. A Pending request is created and one copy is held for you right away.
3. **The librarian reviews it.** Requests appear on the librarian's dashboard to Approve or Reject. Approving starts a 7-day loan. Rejecting puts the copy back on the shelf.
4. **Borrow the book.** Once approved, your dashboard shows the due date. A loan is flagged Overdue when its due date passes.
5. **Renew if you need more time.** Select Renew on an approved loan to add 7 days. Each loan can be renewed up to 2 times, and overdue loans can't be renewed.
6. **Return it.** Bring the book back and the librarian selects Mark returned. The status changes to Returned and the copy is available to others again.

## Librarian tools in detail

- **Books:** each book has a title, author, ISBN, category, number of copies and an optional cover. New books start with every copy available.
- **ISBN check:** the ISBN must be 10 or 13 digits (hyphens are fine) and can't match another book.
- **Copy counts:** when editing, available copies can't be higher than total copies.
- **Returns:** every approved loan has a Mark returned button. It records the return date and adds the copy back to the available count.
- **Cover images:** JPG, PNG, WEBP or GIF up to 2 MB. Replacing, removing or deleting a book also removes the old image file.
- **Categories:** add a new one with an optional description. Names that already exist are refused, whatever the capitalization.
- **Users:** the Users page lists every account. Use Make librarian or Remove librarian to change someone's access. You can't change your own role.

## Built-in safeguards

- Passwords are stored as bcrypt hashes, never as plain text.
- Public sign-up only offers Student and Teacher. The librarian account is built in.
- Librarian-only pages send everyone else back to their dashboard.
- Database queries are prepared and page output is escaped.
- Uploaded images are checked by their content, saved under random names, and the uploads folder can't run PHP.

## Pages in the system

| Page | Who can open it | What it does |
|---|---|---|
| `index.php` | Everyone | The landing page. |
| `items.php` | Everyone | Browse the full book catalog. |
| `register.php` | Everyone | Create a student or teacher account. |
| `login.php` | Everyone | Log in. Librarians go to Manage Books, everyone else to the dashboard. |
| `enduser_dashboard.php` | Logged-in users | Catalog and reservations. Librarians approve or reject requests here. |
| `admin_books.php` | Librarians | Add, edit and delete books, and add categories. |
| `users.php` | Librarians | View users and assign or remove librarian access. |
| `logout.php` | Logged-in users | End your session. |

Supporting files: `db.php` (database connection and starter data), `helpers.php` (cover image upload and display), `schema.sql` (table definitions), `dashboard.php` (redirects to the dashboard).

## Good to know

- A loan lasts 7 days from approval. Late fees are not part of the system.
- Deleting a book is permanent and also removes that book's reservation records.
- Librarian accounts are built in or promoted by an existing librarian.

---

## Setup (README only)

**Requirements:** PHP 7.4 or newer with the `pdo_sqlite` and `mbstring` enabled.

1. Put the project files in a folder, keeping `assets/css/style.css` and `uploads/books/` in place.
2. Start a local server from that folder: `php -S localhost:8000`
3. Open `http://localhost:8000/index.php`.
4. On first load, `db.php` creates `database.sqlite` and fills it with starter data. Make sure the `uploads/books/` folder is writable by the web server.

**Starter accounts** (change these passwords before real use)

| Role | Username | Password |
|---|---|---|
| Librarian | `librarian` | `admin123` |
| Student | `garrett` | `student123` |

If you use an existing `database.sqlite`, its accounts are kept as they are, and the `renewal_count` column is added to `borrow_records` automatically. The default librarian is only recreated if the database has no librarian at all.