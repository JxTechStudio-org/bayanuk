# bayanuk-web

Frontend of Bayanuk (بيانك): a React single-page app in Arabic (right to left) and English (left to right).

This README covers Task 1 (project setup), Task 2 (the landing page) and the frontend part of the root Dockerfile. It explains how to run the project, how it is organised, where every text on the page comes from, and the decisions made where the Technical Plan left a choice open.

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
  vite.config.ts           Vite, Tailwind, the "@/" shortcut, test settings
  public/
    favicon.svg            Browser tab icon (modern browsers)
    favicon-32.png         Browser tab icon (older browsers)
    apple-touch-icon.png   iPhone home screen icon, 180 x 180
  src/
    main.tsx               Entry point
    index.css              Tailwind, the font, the brand colours, shadcn/ui colours
    app/
      App.tsx              Routes: which path shows which page
      i18n.ts              Language setup, direction and tab title
    assets/
      brand/               Official logos, Arabic and English
      landing/             Photos used on the landing page
    components/
      ui/                  shadcn/ui components (button, accordion)
    features/
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
      upload/
        components/
          UploadEntry.tsx  The "Choose a file" button
    lib/
      utils.ts             Helper used by shadcn/ui components
    locales/
      ar.json              Arabic text
      en.json              English text
    test/
      setup.ts             Shared test setup
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
| TanStack Query | Calls to the API | Added with the upload task, the first one that calls the API |
| react-pdf | Document viewer | Added with the viewer task |

Libraries that came with shadcn/ui: `radix-ui` (the components underneath), `class-variance-authority` (component variants), `cn` (merges class names), `tw-animate-css` (open and close animations), `shadcn` (its base CSS) and `lucide-react` (icons).

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
| `accent` (hover background) | `#DDF1F4` | `tint` |
| `ring` (keyboard focus) | `#4FC6D3` | `teal-light` |
| `muted-foreground` (secondary text) | `#4A626A` | A shade of `ink`, contrast about 6:1 on white |
| `muted`, `border`, `input` | `#F6F9FA`, `#DBE7EA` | Light teal greys |

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
| Trust and privacy | `Privacy.tsx` | MVP 9 (training, storage, deletion), MVP 6 (masking of personal identifiers), MVP 4.2 (automatic deletion after a retention period) |
| FAQ | `FAQ.tsx` | Questions written for this page. Answers from MVP 9 (disclaimer), 4.1 and 4.2 (documents, guest mode, sign-in), 8 (chat answers and question limits) |
| Footer | `Footer.tsx` | MVP 9 (the disclaimer, word for word) and the studio name |

### Photos

Real photos, free for commercial use. Resized to at most 1600 pixels at 75% quality, about 150 to 300 KB each.

| File | Used in | Source | Photographer | Licence |
| --- | --- | --- | --- | --- |
| `contract-pages.jpg` | Hero | [Pexels](https://www.pexels.com/photo/person-holding-documents-with-paper-clips-6077915/) | Katrin Bolovtsova | Pexels Licence |
| `contract-review.jpg` | Who it is for (fresh graduates) | [Unsplash](https://unsplash.com/photos/two-men-sitting-at-a-table-with-papers-and-a-pen-Mqc-m8kgxkg) | Amina Atar | Unsplash Licence |
| `contract-handshake.jpg` | Who it is for (job switchers) | [Unsplash](https://unsplash.com/photos/two-people-shaking-hands-over-a-piece-of-paper-4mEyvORkbN0) | Amina Atar | Unsplash Licence |

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
| 17 | No `VITE_API_BASE_URL` in the Docker build yet | The frontend does not call the API yet, so nothing reads this setting. It is added with the first task that calls the API, following decision 1. |
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
| 26 | The automatic deletion periods (24 hours, 30 days) are not shown | MVP 9 states them, but MVP "Open questions" says they are "to be confirmed together with the privacy policy". The page uses MVP 4.2 instead: "automatic deletion after a retention period". The numbers are added to the language files once confirmed. |
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

## Differences from the Technical Plan

| The plan says | This repository has | Note |
| --- | --- | --- |
| A separate `bayanuk-web` repository | A `frontend/` folder inside the `bayanuk` repository | The repository was set up this way. The project name in `package.json` is `bayanuk-web`. |
| A `develop` branch | A `development` branch | Feature branches start from `development`. |

## Open questions

Points that need an answer before or after launch. Nothing here blocks Task 2.

| # | Question | Where it shows | Now |
| --- | --- | --- | --- |
| 1 | Automatic deletion periods (24 hours for guests, 30 days for accounts) | Privacy section | Not shown until confirmed (decision 26). MVP "Open questions" lists it too. |
| 2 | The upload button opens the file picker only | Hero | Connected to the API in the upload task. To confirm this is fine for Task 2. |
| 3 | When to announce the document types "planned after MVP" | Supported documents | Shown as "not supported" (decision 28). |
| 4 | Privacy and Terms pages | Footer | Links added when the pages exist (decision 30). MVP ties the privacy policy to question 1. |
| 5 | Final English spelling and domain of "Bayanuk" | English interface, tab title | Uses "Bayanuk". MVP "Open questions" lists it. |

## Next

- Upload task: connect `UploadEntry` to the API, and add TanStack Query.
