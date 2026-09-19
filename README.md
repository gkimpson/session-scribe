# Session Scribe

Session Scribe is a Laravel and Inertia Vue prototype for recording a consultation and producing a speaker-labelled transcript. The recorder guides the user through consent, microphone access, recording, upload, and transcription.

## Current status

The browser captures real audio and uploads it directly to a private S3 bucket using a short-lived presigned URL. A queued job starts Amazon Transcribe, checks its progress, and saves speaker turns in the database. The page polls for completion, displays the transcript, and can reopen one by its numeric ID in the URL. PII redaction is enabled by default, but automated redaction can miss personal details.

Summary generation is not implemented yet. Review transcripts against the recording before using them.

## Local setup

Requirements: PHP 8.3 or later, Composer, Node.js, npm, SQLite, and Laravel Herd.

```sh
composer install
cp .env.example .env
```

Configure `.env` with `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, and `AWS_BUCKET`. The AWS credentials need permission to upload and read objects in the private bucket and to start and inspect Amazon Transcribe jobs. The bucket also needs CORS rules that allow browser `PUT` requests from the Herd site. Set `RECORDINGS_REDACT_PII=false` only if unredacted transcripts are required.

```sh
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run dev
```

Run a queue worker in a separate terminal so transcription jobs can progress:

```sh
php artisan queue:work
```

Open [Session Scribe](http://session-scribe.test) through Herd. Run `npm run build` to compile frontend assets without the Vite development process.

## Checks

```sh
php artisan test --compact
npm run check
npm run types:check
```

The local `_docs/` directory is excluded from Git.
