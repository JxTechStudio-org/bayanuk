# External Services and API Keys

This document lists the external services used by Bayanuk, their purpose, and where their credentials are stored. No secret values are included.

| Service | Purpose | Development | Production |
|---|---|---|---|
| **Voyage AI** | Generates text embeddings using `voyage-4`. | `backend/.env` | Jenkins Credentials |
| **Google Gemini API** | Contract analysis, classification, chat, and OCR fallback using Gemini 3.8 Flash. | Not configured yet | Not configured yet |
| **Google OAuth** | Enables users to sign in with Google. | Client ID and Client Secret in `backend/.env` | Pending production domain and OAuth URL configuration |
| **Resend** | Sends authentication emails, including magic sign-in links. | `backend/.env` | Jenkins Credentials |

## Notes

- **Google Cloud Services (Gemini API):** Configuration and testing will be completed once Jana enables Google Cloud billing.
- **Key Testing:** All other active keys (Voyage AI, Resend & OAuth) have been successfully tested.
