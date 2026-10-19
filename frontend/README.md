# bayanuk-web

Frontend of Bayanuk (بيانك): a React single-page app in Arabic (right to left) and English (left to right).

This README covers Task 1 (project setup), Task 2 (the landing page), Task 3 (the upload and processing flow) and the frontend part of the root Dockerfile. It explains how to run the project, how it is organised, where every text on the page comes from, and the decisions made where the Technical Plan left a choice open.

## Requirements

- Node.js 22 (LTS)
- npm 10

## Run it

```bash
cd frontend
npm install
npm run dev
```

Vite prints the local address, normally `http://localhost:5173/`. If that port is busy, it uses the next one (`5174`, ...).

To open the site on a phone on the same Wi-Fi:

```bash
npm run dev -- --host
```

Then open the `Network:` address that Vite prints.

### The fake API

The backend is built in parallel, so in development a fake API answers instead (decision 50). It is on by default, from `.env.development`:

```
VITE_API_MOCK=true
```

The browser console shows `[MSW] Mocking enabled.` when it runs. It follows the integration contract (`docs/integration-contract.md` at the root of the repository): it sets the CSRF cookie, issues guest tokens, refuses a request without them (`419`, `400`), and answers another guest's document with `404`. Document IDs are UUIDs, as in the real API. A document moves one status every 2 seconds, and the file name picks the outcome:

| File name | Outcome |
| --- | --- |
| Any PDF or DOCX | Ready after about 12 seconds |
| Contains `reject` (for example `reject-lease.pdf`) | Rejected, `status_detail` = `not_employment_document` |
| Contains `fail` (for example `fail-scan.pdf`) | Failed, `status_detail` = `file_unreadable` |
| Contains `budget` (for example `budget-offer.pdf`) | Paused while analyzing, `status_detail` = `ai_budget_reached` |

The fake documents and guest tokens live in memory, so refreshing the processing page gets a new guest token and shows "Document not found". The real API keeps them in the database.

To use the real API, set `VITE_API_MOCK=false` in a `.env.development.local` file (not committed). Vite then sends `/api` and `/sanctum` to the backend on `http://localhost:8000` (decision 73). The production build never includes the fake API (decision 76).

## Commands

| Command | What it does |
| --- | --- |
| `npm run dev` | Starts the development server |
| `npm test` | Runs the tests once (Vitest) |
| `npm run lint` | Checks the code with ESLint |
| `npm run build` | Type checks with `tsc`, then builds into `dist/` |

`test`, `lint` and `build` are the checks the Jenkins pipeline runs (Technical Plan, section 10). All three pass on this branch.

## Docker

The frontend build stage is in the root `Dockerfile`, as the `frontend-build` stage. To build and check it from the repository root:

```bash
docker build --target frontend-build -t bayanuk-frontend .
docker run --rm bayanuk-frontend ls dist
```

The built site is in `/app/frontend/dist` inside the `frontend-build` stage. The final runtime stage copies it from there.

## Folder structure

```
frontend/
  index.html               Page shell. Starts as Arabic, right to left. Links the icons.
  components.json          shadcn/ui settings (Radix, RTL, where components go)
  vite.config.ts           Vite, Tailwind, the "@/" shortcut, the dev proxy, test settings
  public/
    favicon.svg            Browser tab icon (modern browsers)
    favicon-32.png         Browser tab icon (older browsers)
    apple-touch-icon.png   iPhone home screen icon, 180 x 180
    mockServiceWorker.js   Made by MSW for the fake API. Not edited by hand. Removed from the build
  .env.development         Turns the fake API on in development
  src/
    main.tsx               Entry point. Starts the fake API first in development
    index.css              Tailwind, the font, the brand colours, shadcn/ui colours
    app/
      App.tsx              Routes: which path shows which page
      queryClient.ts       TanStack Query settings (retries)
      i18n.ts              Language setup, direction and tab title
    assets/
      brand/               Official logos, Arabic and English
      landing/             Photos used on the landing page
    components/
      ui/                  shadcn/ui components (button, accordion)
    features/
      analysis/
        types.ts           Document statuses and the status response
        api/
          getDocumentStatus.ts   GET /documents/{id}/status
        hooks/
          useDocumentStatus.ts   Asks for the status every 2 seconds until processing ends
        utils/
          stages.ts        Maps the statuses to the 4 progress stages
        components/
          AnalysisLayout.tsx     The frame of the pages after upload: header, card, privacy line
          ProcessingStages.tsx   The 4 stages: done, in progress, waiting
          ProcessingNotice.tsx   The line under the stages: in the queue, paused, or offline
        pages/
          ProcessingPage.tsx     /d/:id, shown right after upload
          ResultPage.tsx         /d/:id/result, placeholder until the result task
      landing/
        components/        One file per section of the landing page
          Header.tsx
          Hero.tsx
          TargetUsers.tsx
          SupportedFiles.tsx
          HowItWorks.tsx
          Privacy.tsx
          FAQ.tsx
          Footer.tsx
        pages/
          LandingPage.tsx  Puts the sections together, top to bottom
      shared/
        components/
          LanguageSwitcher.tsx   Used by every page
          Logo.tsx               The logo, chosen by language
          AppHeader.tsx          Header for the pages after the landing page
      upload/
        api/
          uploadDocument.ts      POST /documents
        hooks/
          useUploadDocument.ts   Sends the file, then opens the processing page
        utils/
          validateFile.ts        Type and size check before upload
        components/
          UploadEntry.tsx  The "Choose a file" button, and drop anywhere on the page
    lib/
      api.ts               Every call to the API: base path, headers, CSRF, guest token, error format
      guestToken.ts        Keeps the guest token the backend issued
      utils.ts             The `cn` helper, created by the shadcn CLI (see decision 48)
    mocks/
      handlers.ts          The fake API (development only)
      browser.ts           Starts it in the browser
    locales/
      ar.json              Arabic text
      en.json              English text
    test/
      setup.ts             Shared test setup
      renderWithProviders.tsx   Renders a component with TanStack Query and the router
```

Tests sit next to the file they test (for example `FAQ.test.tsx` beside `FAQ.tsx`).

The structure follows the Technical Plan (section 4) and the studio's convention from the invoi project:

- **`features/<feature>/components`** for the parts of one feature, and **`features/<feature>/pages`** for the page that puts them together. The landing page is built this way, one file per section, and `LandingPage` is imported in `App.tsx`.
- **`features/shared/components`** for our own components used by more than one page.
- **`components/ui`** for shadcn/ui components, as the plan says (`components/  shared UI ... from shadcn/ui`).
- **`app/`, `lib/` and `locales/`** as named in the plan.

`@/` is a shortcut for `src/`, so imports read `@/features/landing/components/Hero` instead of `../../features/landing/components/Hero`.

## Libraries

Every frontend library named in the Technical Plan (section 3), and its status.

| Library | Purpose | Status |
| --- | --- | --- |
| React + TypeScript + Vite | App, types, build tool | Installed |
| ESLint | Code checks | Installed |
| Tailwind CSS 4 | Styling | Installed |
| Readex Pro | Brand font, Arabic and Latin | Installed, served from the project |
| react-i18next | Arabic and English text | Installed |
| React Router | Pages and paths | Installed |
| Vitest + Testing Library | Tests | Installed |
| shadcn/ui (Radix) | Shared components | Installed in Task 2, with right-to-left support. Components in use: `button`, `accordion` |
| TanStack Query | Calls to the API | Installed in Task 3. Used for the upload and the status checks |
| react-pdf | Document viewer | Added with the viewer task |

Libraries that came with shadcn/ui: `radix-ui` (the components underneath), `class-variance-authority` (component variants), `cn` (merges class names), `tw-animate-css` (open and close animations), `shadcn` (the CLI that adds components, and its base CSS) and `lucide-react` (icons).

One library not named in the plan: **MSW** (Mock Service Worker), for the fake API in development (decision 50). It is in `devDependencies` and is not part of the production build.

`dependencies` holds the libraries whose code runs in the browser. `devDependencies` holds the tools used while writing and building the site: Vite, TypeScript, ESLint, the test tools, Tailwind CSS and the `shadcn` CLI (decision 45).

## Brand

Colours come from the Bayanuk identity file and are defined once, in `src/index.css`.

| Name | Value | Use |
| --- | --- | --- |
| `ink` | `#0E2A33` | Dark background, main text |
| `teal` | `#1597A8` | Brand colour for icons and shapes |
| `teal-light` | `#4FC6D3` | Teal on dark backgrounds |
| `tint` | `#DDF1F4` | Light background touch |
| `teal-deep` | `#0C6F7C` | Teal for text and links on light backgrounds (see decision 5) |

They are used as Tailwind classes, for example `bg-ink` or `text-teal-light`.

shadcn/ui components read their own colour names. They are set to the brand in `:root` in `src/index.css`:

| shadcn/ui name | Value | Where it comes from |
| --- | --- | --- |
| `primary` (main buttons) | `#0C6F7C` | `teal-deep` |
| `foreground` (text) | `#0E2A33` | `ink` |
| `ring` (keyboard focus) | `#4FC6D3` | `teal-light` |
| `muted-foreground` (secondary text) | `#4A626A` | A shade of `ink`, contrast about 6:1 on white |
| `muted`, `border` | `#F6F9FA`, `#DBE7EA` | Light teal greys |

The logo in the header follows the language: the Arabic logo with the Arabic interface and the English logo with the English one, as the identity file says. The logo files are used as delivered, unchanged.

## Languages

- Arabic is the default. The choice is kept in the browser for the next visit.
- Changing the language also changes the page direction (`rtl` or `ltr`), the logo and the browser tab title.
- All text lives in `src/locales/ar.json` and `en.json`, grouped by section (`hero`, `faq`, ...). The two files must have the same keys, including nested ones; a test checks this.

## Landing page

Sections, top to bottom, and the source of their text. Every sentence on the page comes from the MVP Definition. The only exceptions are the button labels, the product name, and the photo descriptions read by screen readers (`photoAlt`), which describe what is in each photo.

| Section | File | Text from |
| --- | --- | --- |
| Header | `Header.tsx` | Section titles below. "الخصوصية" is the short form of the section 9 title |
| Hero | `Hero.tsx` | Title: MVP 1 (the one-line promise, with its Arabic). Subtitle: MVP 1 (product overview). Privacy line: MVP 9. Example card: MVP 6 (example column: probation 90, basic salary 8,000, employee notice 30, non-compete 12 months) |
| Who it is for | `TargetUsers.tsx` | MVP 3, the two primary segments (fresh graduates, job switchers) |
| Supported documents | `SupportedFiles.tsx` | MVP 4.1 (document types) and 4.2 (PDF or DOCX, up to 10 MB and 30 pages) |
| How it works | `HowItWorks.tsx` | MVP 5, steps 2 to 5. The two example questions are quoted from step 5 |
| Trust and privacy | `Privacy.tsx` | MVP 9 (training, storage, deletion, and the automatic deletion periods), MVP 6 (masking of personal identifiers) |
| FAQ | `FAQ.tsx` | Questions written for this page. Answers from MVP 9 (disclaimer), 4.1 and 4.2 (documents, guest mode, sign-in), 8 (chat answers and question limits) |
| Footer | `Footer.tsx` | MVP 9 (the disclaimer, word for word) and the studio name |

### Photos

Real photos, free for commercial use. Resized to at most 1600 pixels at 75% quality, about 150 to 300 KB each.

| File | Used in | Source | Photographer | Licence |
| --- | --- | --- | --- | --- |
| `contract-pages.jpg` | Hero | [Pexels](https://www.pexels.com/photo/person-holding-documents-with-paper-clips-6077915/) | Katrin Bolovtsova | Pexels Licence |
| `contract-review.jpg` | Who it is for (fresh graduates) | [Unsplash](https://unsplash.com/photos/two-men-sitting-at-a-table-with-papers-and-a-pen-Mqc-m8kgxkg) | Amina Atar | Unsplash Licence |
| `contract-handshake.jpg` | Who it is for (job switchers) | [Unsplash](https://unsplash.com/photos/two-people-shaking-hands-over-a-piece-of-paper-4mEyvORkbN0) | Amina Atar | Unsplash Licence |

## Upload and processing

The flow from MVP 5, steps 2 and 3. The API follows the integration contract (`docs/integration-contract.md`).

1. **Choose or drop a file.** The "Choose a file" button opens the file picker. A file can also be dropped anywhere on the page.
2. **Check it in the browser.** PDF or DOCX, up to 10 MB (MVP 4.2). If not, a message shows under the button and nothing is sent.
3. **Send it.** `POST /api/v1/documents`, as a form with one field, `file`. The API answers `202` with the new document's `id`.
4. **Show the processing page** at `/d/{id}`, with the file name. It asks `GET /api/v1/documents/{id}/status` every 2 seconds and stops when processing ends, or on `404` or `410`.
5. **Explain any wait** with one line under the stages: in the queue (`uploaded`), paused by the daily AI budget, or offline.
6. **End in one of these states:** `ready` opens `/d/{id}/result`. `rejected` and `failed` show a title and the translated reason from `status_detail`. `404` shows "Document not found", and `410` shows "This document was deleted".

The API has 9 statuses (Database Schema). The 6 processing statuses map to the 4 stages the visitor sees (MVP 5, step 3). The Database Schema says: "The frontend polls the status and maps each value to a friendly progress label."

| API status | Stage shown |
| --- | --- |
| `uploaded`, `scanning` | Checking file |
| `extracting` | Reading document |
| `classifying`, `analyzing` | Finding key terms |
| `indexing` | Looking for points that need attention |

Every call goes through `lib/api.ts` (contract, sections 2, 4 and 5). It:

- adds the `/api/v1` base path, the interface language (`Accept-Language`) and the cookies;
- gets the CSRF cookie before the first request that changes data, and sends it back in `X-XSRF-TOKEN`;
- asks the backend for a guest token the first time (`POST /guest-token`), keeps it, and sends it in `X-Guest-Token`;
- repeats a request once after `419` (new CSRF cookie) or `invalid_guest_token` (new guest token);
- turns the error format `{ "error": { "code", "message" } }` into an `ApiError` with the HTTP status, the code and the message.

### Text sources

| Text | From |
| --- | --- |
| The four stages | MVP 5, step 3, word for word in English |
| "Bayanuk supports job offers and employment contracts only" (reason `not_employment_document`) | MVP 4.1 |
| "We could not read this file" (reason `file_unreadable`) | Technical Plan, section 5, step 2 |
| "More than 30 pages" (reason `too_many_pages`) | MVP 4.2 |
| "Your document is in the queue and will start shortly" | Integration contract, section 6 |
| The connection lost message | The manager's Task 3 review: the connection is lost and the system is trying to reconnect |
| The paused message | AI Stack v2.0, section 4.3: analyses "pause with a clear message until the next day" |
| File type and size errors | MVP 4.2 |
| The privacy line under the processing card | The landing page line, MVP 9 |
| The other end-state titles, the other reasons (`unsafe_file`, `analysis_failed`), the buttons, "Uploading", "Drop the file here" | Interface labels, our choice |

## Decisions

Choices made where the plan did not specify, with the reason for each.

### Task 1

| # | Decision | Reason |
| --- | --- | --- |
| 1 | A library is installed when there is code that uses it | The task asks for the libraries "you need". It keeps the project small and every dependency explainable. The table above shows when each remaining library comes in. |
| 2 | Tailwind CSS version 4 | The plan names Tailwind without a version. This is a new project with no older code to stay compatible with, and version 4 needs less setup. |
| 3 | The font is served from the project, not from Google Fonts | The product promises privacy, so a visitor's browser should not contact a third party just to load a font. This also matches the plan's approach of running everything on the studio server. |
| 4 | Colour names `ink`, `teal`, `teal-light`, `tint` | Taken from the names in the identity file (حبر، فيروزي، فيروزي فاتح، لمسة خلفية). |
| 5 | An extra colour, `teal-deep`, for text | The MVP Definition (section 10) requires text contrast of at least 4.5:1. Brand teal on white is about 3.5:1, so it is kept for icons and shapes. `teal-deep` is about 5.9:1. The identity is unchanged. |
| 6 | First visit is always Arabic | The launch market is Saudi Arabia and the database default for `locale` is `ar`. Simpler and more predictable than guessing from the browser. |
| 7 | The language choice is saved in the browser | Visitors should not have to choose on every visit. |
| 8 | The site still works when browser storage is blocked | Some users block storage. Without this, the page would fail to load for them. They only lose the saved choice. |
| 9 | The tab title follows the language | "بيانك" in Arabic, "Bayanuk" in English. |
| 10 | ~~A `pages/` folder next to the plan's folders~~ | Replaced in Task 2 by decision 19. |
| 11 | Folders are created only when they have a file | Git does not store empty folders, and an empty folder suggests work that is not there. |
| 12 | The `@/` import shortcut | Clearer imports that do not break when a file moves. shadcn/ui also expects it. |
| 13 | Tests are set up now, in Task 1, although the task list does not name them | The Technical Plan names Vitest and Testing Library (section 9), and the Jenkins pipeline runs Vitest on every push (section 10). Setting it up with the project means `npm test` exists from the first push, and it is easier to add while the project is small. |
| 14 | Test files sit next to the file they test | `FAQ.test.tsx` is beside `FAQ.tsx`, so tests are easy to find. |
| 15 | First tests cover the language files and the direction switch | These are the two things that existed in Task 1, and the plan asks for checks in both RTL and LTR. |
| 16 | `npm ci` in the Docker build, not `npm install` | `npm ci` installs exactly the versions in `package-lock.json` and stops if the two disagree, so the server builds what was tested locally. The backend stage of the studio's existing Dockerfile uses it too. |
| 17 | ~~No `VITE_API_BASE_URL` in the Docker build yet~~ | Not needed. Decided in Task 3 by decision 52. |
| 18 | A root `.dockerignore` that skips `node_modules`, `dist` and `.git` | Without it, the build would copy the macOS `node_modules` into the Linux image and break the build. It also keeps the files sent to Docker small. It sits at the root so it covers the backend too. |

### Task 2

| # | Decision | Reason |
| --- | --- | --- |
| 19 | The landing page follows the invoi structure: `features/landing/components` (one file per section) and `features/landing/pages/LandingPage.tsx`, imported in `App.tsx` | The studio's convention, so anyone who knows invoi finds their way here. Smaller files are easier to review, and changing one section does not touch the others. |
| 20 | The landing header is in `features/landing/components` | Its links point to sections of this page only. Other pages will have their own header, as invoi's dashboard pages use `NavBar` and not the landing `Header`. |
| 21 | The language switch is in `features/shared/components` | The manager asked for it in every page. `features/shared` is where invoi keeps components used by several pages. invoi has no language switch (it is Arabic only), so its place is our choice. |
| 22 | shadcn/ui components stay in `src/components/ui` | The plan puts shadcn/ui in `components/`, and the shadcn CLI adds new components there. |
| 23 | The "Choose a file" button is in `features/upload` | The plan names `features/upload` for the upload. The button opens the file picker and accepts PDF and DOCX only. Sending the file to the API comes with the upload task. |
| 24 | One upload button on the page, in the hero. No closing "call to action" section with a second one | MVP 5, step 1: "The user sees the promise, the privacy message, and one upload button." This is a difference from invoi, which has two. |
| 25 | Every sentence on the page comes from the MVP Definition | The page describes the product, and the MVP Definition is the agreed description. The landing page section above lists the source of each text. |
| 26 | The privacy section shows the automatic deletion periods: 24 hours for guests, 30 days for signed-in users unless they choose to keep it | MVP 9 states them, with the same exception (the `keep_documents` setting in the Database Schema). They were first hidden because MVP "Open questions" lists them as "to be confirmed". The manager confirmed them in the Task 2 review. If they change, only the `privacy.points.retention` text in `ar.json` and `en.json` changes. |
| 27 | The notice example in the hero card is 30 days | The MVP example (section 6) is "30 / 60": 30 for the employee, 60 for the employer. The card says "notice if you resign", which is the employee's. |
| 28 | "Planned after MVP" document types are shown as "not supported", with no "coming soon" | A "coming soon" is a promise to visitors. When to announce them is the manager's call. |
| 29 | The FAQ questions start closed | Tidier first view. It also avoids an answer being cut off when it opens before the font loads. |
| 30 | No Privacy or Terms links in the footer yet | Those pages are not written yet. A link to a missing page looks broken. |
| 31 | The header shows four links (who it is for, how it works, privacy, FAQ) | Five links felt crowded. "Supported documents" is reachable by scrolling, and privacy uses a shorter label than its section title. |
| 32 | The official logo files, unchanged, chosen by language; SVG, not PNG | SVG stays sharp on every screen and is smaller. The identity file pairs each logo with its interface language. |
| 33 | Favicon set: `favicon.svg`, `favicon-32.png`, `apple-touch-icon.png` (180) | Modern browsers use the SVG, older ones the PNG, and iPhones look for `apple-touch-icon.png` by name. All from the delivered logo kit. |
| 34 | shadcn/ui set up with Radix, right-to-left support, and a custom preset | The plan says "shadcn/ui (Radix)". The ready-made presets bring their own font. |
| 35 | The Inter font added by the shadcn CLI was removed | The plan says Readex Pro ("Brand: Readex Pro font"). The CLI had set Inter over it. The manager confirmed Readex Pro stays. |
| 36 | shadcn/ui colours set to the brand (table in "Brand") | Otherwise buttons are black and grey. |
| 37 | `lucide-react` for icons | The icon library shadcn/ui uses. The plan names no icon library. |
| 38 | The "Fast Refresh" lint rule is off in `src/components/ui` only | shadcn/ui files export helpers next to components (for example `buttonVariants`). Keeping them as the CLI writes them makes future updates simple. The rule stays on for our own code. |
| 39 | The `@/` shortcut is also in the root `tsconfig.json` | The shadcn CLI reads it from there. |
| 40 | The attention colour (non-compete in the hero card) is Tailwind's amber | The identity file has no warning colour. Amber 800 on amber 50 passes the 4.5:1 contrast rule. |
| 41 | Real photos from Pexels and Unsplash, resized to 1600 pixels at 75% quality, lazy loaded below the first screen | The manager asked for real photos, not AI-generated. Smaller files load fast on phones. Sources and licences are in the "Photos" table. |
| 42 | Small motion only: the hero card items appear one by one, cards lift on hover | The manager asked for a page with some life. All motion stops for visitors who turn on "reduce motion". |
| 43 | The language test checks nested keys | Texts are now grouped by section. Checking only the top-level keys would miss a missing translation inside a group. |
| 44 | Mobile first | Classes without a prefix are for phones, `md:` and `lg:` for wider screens. Checked on a real phone. |

### Task 2 review changes

| # | Decision | Reason |
| --- | --- | --- |
| 45 | `shadcn`, `tailwindcss` and `@tailwindcss/vite` moved to `devDependencies` | The manager's review. They are tools used while building, like Vite: none of their JavaScript runs in the browser. `shadcn` is the CLI that adds components; the site only uses its base CSS (`shadcn/tailwind.css`), which Tailwind reads at build time. The Docker build runs `npm ci`, which installs `devDependencies` too, so the build is unchanged. |
| 46 | `npm audit` warnings are known and left as they are | `npm audit --omit=dev` (the site's own libraries) finds 0 vulnerabilities. `npm audit` shows 7 high warnings, all in `braces`, which comes with the `shadcn` CLI (`shadcn` → `fast-glob` → `micromatch` → `braces`). It only runs when adding components on a developer's machine, and is not in the built site. Do not run `npm audit fix --force`: it "fixes" this by downgrading `shadcn` to version 1.0.0. To check again after updating `shadcn`. |
| 47 | Unused shadcn/ui theme settings removed: dark mode (`.dark` and the `dark` variant), `sidebar-*`, `chart-*`, `card-*`, `popover-*`, `accent-*`, `input`, `font-heading`, and the `sm`, `3xl` and `4xl` radius sizes | The manager's review. Each name was checked against the built CSS: none of these is used by the page or by the two components (`button`, `accordion`). The `dark:` classes in `button.tsx` were removed too: without the `.dark` setting, Tailwind would apply them whenever the phone or computer is in dark mode, and the outline button would change colour. `secondary` and `destructive` stay because `button.tsx` uses them in its variants. When a new shadcn/ui component needs a removed name, it is added back with a brand value. |
| 48 | `src/lib/utils.ts` stays | The shadcn CLI created it, and `components.json` points the CLI to it (`"utils": "@/lib/utils"`). Removing it could break `npx shadcn add` for the next component. Since Task 3, our own components import `cn` from it too. |

### Task 3

| # | Decision | Reason |
| --- | --- | --- |
| 49 | TanStack Query for every API call: `useMutation` for the upload, `useQuery` for the status | The plan names it. It gives loading and error states, and repeats the status check every 2 seconds with one setting (`refetchInterval`), which stops when processing ends. |
| 50 | A fake API with MSW, in development only | The backend is built at the same time. The fake API follows the plan's contract, so the frontend can be built and tested now, without waiting and without touching the backend folder. It runs only when `VITE_API_MOCK=true`, and its code is never in the production build (checked in `dist`). MSW's `mockServiceWorker.js` sits in `public/`, and is removed from `dist` after the build (decision 76). |
| 51 | The fake outcome is chosen by the file name (`reject`, `fail`) | The simplest way to try all three endings by hand, with no extra screen or setting. |
| 52 | The API base is the relative path `/api/v1`, with no `VITE_API_BASE_URL` | The plan serves the frontend and `/api/v1` from the same nginx (section 2), so the browser reaches the API on the same address. It also keeps the Sanctum cookie on the same site. |
| 53 | Every call sends `credentials: 'include'` | Signed-in users use Sanctum SPA cookie sessions (plan, section 7). Guests are not affected. |
| 54 | ~~The guest token is 32 random bytes made in the browser~~ Replaced by decision 71 | The Integration Contract card decided that the backend issues it. |
| 55 | The browser checks type and size only; the API checks the page count (30) | A browser cannot count DOCX pages reliably. The plan's pipeline checks pages on the server (section 5, step 1), and the server checks everything again anyway. |
| 56 | A file is also accepted by its extension (`.pdf`, `.docx`), whatever type the browser reports | Some systems report an empty or unusual type for these files. The server checks the real content anyway (plan, section 5, step 1). |
| 57 | A file can be dropped anywhere on the page, with a full-page "Drop the file here" layer | MVP 5, step 2: "The user drops a PDF or DOCX". A small drop zone is easy to miss on a long page. |
| 58 | A failed upload is not repeated on its own; a failed status check is tried once more | Sending a file twice could create two documents. Asking for the status again is safe. |
| 59 | The API's error message is shown when it sends one, and ours otherwise | The plan says API errors carry "a translated message". Ours covers network errors and an empty message. `status_detail` is a code with our own translated text (decision 74). |
| 60 | The processing page shows the file name | The Database Schema says `original_filename` is "shown to the owner only". It helps a visitor who uploads two offers to compare. The name is passed with the page change, so it is not sent anywhere. |
| 61 | No progress bar and no time estimate on the processing page | The four stages already show the progress. The MVP's "under 60 seconds" is a target for the team, not a promise to visitors (same reason as decision 28). |
| 62 | `Logo` and `AppHeader` in `features/shared/components` | The processing page needs the logo and the language switch (decision 21) without the landing page links. The landing `Header` now uses the same `Logo`. |
| 63 | `ResultPage` is a placeholder that says the analysis is ready | Processing has to end somewhere. The result dashboard is a separate task. |
| 64 | `renderWithProviders` for tests, with an optional starting address | Components now need TanStack Query and the router. One helper avoids repeating that in every test. Pages that read the address (`/d/123`) start there. |
| 65 | Only a `404` shows "Document not found" (and a `410` "This document was deleted", decision 75), and the checks stop there. Any other error keeps the stages on screen, and the next check tries again | A dropped connection for a few seconds should not tell the visitor their document is gone. Without the stop, a missing document would be asked about every 2 seconds for as long as the page is open. |
| 66 | `AnalysisLayout` in `features/analysis/components` | The processing and result pages share the same frame. One place to change it, and the pages only hold their own content. |
| 67 | Tests for the processing page cover every state on the card: stages, ready, rejected, failed, not found, and a dropped connection. They replace `fetch` inside the test | These are the states the task asks for. Replacing `fetch` tests the real path through `lib/api.ts`, without the fake API. |
| 68 | One upload at a time: while a file is uploading, the button is disabled and a dropped file is ignored | Otherwise a second drop during an upload sends a second document. A test covers it. |
| 69 | ~~`ApiError` keeps the status and the message only~~ Replaced by decision 72 | `lib/api.ts` now reads the code (`invalid_guest_token`). |
| 70 | ~~Only the rejected state has our own fallback text~~ Replaced by decision 74 | Every reason now has its own text. |

### Task 3 review changes

The manager's Task 3 review, and the integration contract (`docs/integration-contract.md`).

| # | Decision | Reason |
| --- | --- | --- |
| 71 | The backend issues the guest token (`POST /guest-token`). The frontend asks for it before the first request, keeps it in the browser, and sends it only in `X-Guest-Token`. Requests that start together share one call | The Integration Contract card (contract, section 5). Sharing the call means two requests at the same moment never get two tokens. If storage is blocked, the token is kept for the visit, as in decision 8. |
| 72 | `ApiError` keeps the HTTP status, the code and the message | The code decides what to do: `invalid_guest_token` gets a new token. The status still decides the end states. |
| 73 | The CSRF flow: `GET /sanctum/csrf-cookie` before the first request that changes data, the token in `X-XSRF-TOKEN`, and one repeat after `419`. A Vite proxy sends `/api` and `/sanctum` to `localhost:8000` | Contract, section 2. The proxy puts the frontend and the backend on one address in development, so the Sanctum cookies work, as nginx does in production. |
| 74 | `status_detail` is a code, and each code has its own text in `processing.reasons`. A code this version does not know shows the title only | Contract, section 6. The backend sends the same code in both languages, and the visitor never sees a raw code. The failed title became "We could not analyze this file", because a failure is not always about reading. |
| 75 | A `410` (`document_expired`) stops the checks and shows "This document was deleted", in the calm style of a rejected document | Contract, section 6. A deleted document is expected behaviour after the retention period, not an error. The contract does not set the period, so the text does not name one. |
| 76 | `mockServiceWorker.js` is removed from `dist` after the build, by a small plugin in `vite.config.ts` | The manager's review and contract, section 12: the mock is never part of the production build. Vite copies all of `public/` to `dist`, and the mock code itself was already left out. The file stays in `public/`, so the fake API still works in development and tests. |
| 77 | One line under the stages explains a wait: offline first, then paused, then in the queue | The manager asked that the visitor always knows what is happening. Only one line shows, the most important one. |
| 78 | "Offline" shows at once when the browser reports no connection, or after 3 failed checks in a row when the API cannot be reached. The check repeats every 2 seconds until it works, and the line goes away | The manager's review: a message "after a few failed attempts". The browser knows at once when it is offline. 3 checks take about 4 seconds, enough to skip a short drop. |
| 79 | A `4xx` answer is never retried. A server error (`5xx`) is retried once | A `404` or `410` will not change, so the page shows it at once. |
| 80 | While the daily AI budget pauses a document, the status is checked every 60 seconds instead of 2 | Contract, section 6. A page left open overnight would otherwise send thousands of requests. |
| 81 | Each test starts with a stored guest token. `lib/api.test.ts` tests the token and CSRF flow on its own | The page tests stay about their own screens. The flow is tested once, in one place, with every case: new guest, shared call, CSRF header, `419`, rejected token, error format. |

## Differences from the Technical Plan

| The plan says | This repository has | Note |
| --- | --- | --- |
| A separate `bayanuk-web` repository | A `frontend/` folder inside the `bayanuk` repository | The repository was set up this way. The project name in `package.json` is `bayanuk-web`. |
| A `develop` branch | A `development` branch | Feature branches start from `development`. |

## Open questions

Points that need an answer before or after launch. Nothing here blocks Task 2.

| # | Question | Where it shows | Now |
| --- | --- | --- | --- |
| 1 | Automatic deletion periods (24 hours for guests, 30 days for accounts) | Privacy section | Answered: confirmed by the manager and shown on the page (decision 26). |
| 2 | The upload button opens the file picker only | Hero | Answered: the manager confirmed the file picker is enough for Task 2. The API comes with Task 3. |
| 3 | When to announce the document types "planned after MVP" | Supported documents | Shown as "not supported" (decision 28). |
| 4 | Privacy and Terms pages | Footer | Links added when the pages exist (decision 30). |
| 5 | Final English spelling and domain of "Bayanuk" | English interface, tab title | Uses "Bayanuk". MVP "Open questions" lists it. |

### For the backend

The assumptions listed here in Task 3 are now agreed with the backend in the integration contract (`docs/integration-contract.md`).

## Next

- Connect to the real API once the backend endpoints are ready: set `VITE_API_MOCK=false` (see "The fake API").
- Result task: the result dashboard at `/d/{id}/result`.
