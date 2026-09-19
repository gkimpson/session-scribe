# Session Scribe

Session Scribe is a Laravel and Inertia Vue prototype for recording a consultation and drafting session notes. The current screen guides the user through consent, microphone access, recording, a transcript, and an editable summary with three detail levels.

## Current status

The browser requests real microphone access, but the upload, transcription, and summary stages are simulated. The transcript and summaries use sample consultation data in `resources/js/lib/fakeConsultation.ts`; recorded audio is not uploaded or saved. Do not use the generated notes as a record of an actual consultation.

## Local setup

Requirements: PHP 8.3 or later, Composer, Node.js, npm, SQLite, and Laravel Herd.

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run dev
```

Open [Session Scribe](http://session-scribe.test) through Herd. Run `npm run build` to compile frontend assets without the Vite development process.

## Checks

```sh
php artisan test --compact
npm run check
npm run types:check
```

The local `_docs/` directory is excluded from Git.
