# bayanuk-web

Frontend of Bayanuk (بيانك): a React single-page app in Arabic (right to left) and English (left to right).

This README covers Task 1, project setup. It explains how to run the project, how it is organised, and the decisions made where the Technical Plan left a choice open.

## Requirements

- Node.js 22 (LTS)
- npm 10

## Run it

```bash
cd frontend
npm install
npm run dev
```

Vite prints the local address, normally `http://localhost:5173/`.

## Commands

| Command | What it does |
| --- | --- |
| `npm run dev` | Starts the development server |
| `npm test` | Runs the tests once (Vitest) |
| `npm run lint` | Checks the code with ESLint |
| `npm run build` | Type checks with `tsc`, then builds into `dist/` |

`test`, `lint` and `build` are the checks the Jenkins pipeline runs (Technical Plan, section 10). All three pass on this branch.

## Folder structure

```
frontend/
  index.html          Page shell. Starts as Arabic, right to left.
  vite.config.ts      Vite, Tailwind, the "@/" shortcut, test settings
  src/
    main.tsx          Entry point
    index.css         Tailwind, the font, and the brand colours
    app/
      App.tsx         Routes: which path shows which page
      i18n.ts         Language setup, direction and tab title
    pages/
      HomePage.tsx    Placeholder page, replaced in Task 2
    locales/
      ar.json         Arabic text
      en.json         English text
    test/
      setup.ts        Shared test setup
```

This follows the feature-based structure in the Technical Plan (section 4). The plan's other folders (`features/`, `components/`, `lib/`) are created when the first file that belongs in them is written.

`@/` is a shortcut for `src/`, so imports read `@/pages/HomePage` instead of `../../pages/HomePage`.

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
| shadcn/ui (Radix) | Shared components | Added in Task 2 with the first real component |
| TanStack Query | Calls to the API | Added with the upload task, the first one that calls the API |
| react-pdf | Document viewer | Added with the viewer task |

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

## Languages

- Arabic is the default. The choice is kept in the browser for the next visit.
- Changing the language also changes the page direction (`rtl` or `ltr`) and the browser tab title.
- All text lives in `src/locales/ar.json` and `en.json`. The two files must have the same keys; a test checks this.

## Decisions

Choices made where the plan did not specify, with the reason for each.

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
| 10 | A `pages/` folder next to the plan's folders | The plan does not say where a whole page goes. A page combines several features, so it gets its own place. |
| 11 | Folders are created only when they have a file | Git does not store empty folders, and an empty folder suggests work that is not there. |
| 12 | The `@/` import shortcut | Clearer imports that do not break when a file moves. shadcn/ui also expects it. |
| 13 | Tests are set up now, in Task 1, although the task list does not name them | The Technical Plan names Vitest and Testing Library (section 9), and the Jenkins pipeline runs Vitest on every push (section 10). Setting it up with the project means `npm test` exists from the first push, and it is easier to add while the project is small. |
| 14 | Test files sit next to the file they test | `HomePage.test.tsx` is beside `HomePage.tsx`, so tests are easy to find. |
| 15 | First tests cover the language files and the direction switch | These are the two things that exist now, and the plan asks for checks in both RTL and LTR. |

## Differences from the Technical Plan

| The plan says | This repository has | Note |
| --- | --- | --- |
| A separate `bayanuk-web` repository | A `frontend/` folder inside the `bayanuk` repository | The repository was set up this way. The project name in `package.json` is `bayanuk-web`. |
| A `develop` branch | A `development` branch | Feature branches start from `development`. |

## Next

- Task 2: the landing page. Replaces `HomePage.tsx`, adds shadcn/ui, and replaces the default Vite favicon with the Bayanuk icon.
