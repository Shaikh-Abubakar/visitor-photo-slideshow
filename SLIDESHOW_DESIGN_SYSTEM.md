# Public Slideshow — Design System and Behaviour

## Purpose

The public display is a quiet, gallery-like presentation for a shared screen, reception area, or event display. It prioritizes the photograph over interface chrome, preserves the complete image, and makes playback understandable without requiring a mouse or JavaScript.

## Project constraints retained

- PHP 8.2+, PDO, MySQL/MariaDB, Apache.
- HTML and local CSS only; no JavaScript, frontend framework, remote stylesheet, external font, or build step.
- The current database schema and photo lifecycle are retained.
- The public page uses `slideshow_photo.php`, not a direct path into private storage.
- The selection and image-serving helpers continue to require a registered visitor, an active photo, administrator approval, the matching consent record and consent version, and consent that has not been withdrawn.
- No visitor name, visitor code, email, or other personal profile data is shown on the public display.

## Visual language

| Token | Value | Use |
|---|---|---|
| Background | `#101511` | Dark gallery canvas |
| Raised panel | `#171e19` / `#202a22` | Controls and empty states |
| Main text | `#f4f3ec` | High-contrast headings |
| Secondary text | `#aab3a8` | Supporting labels |
| Accent | `#c7d7a5` | Playback state, progress, active option |
| Hairline | `rgba(236,241,229,.12)` | Quiet separators |
| Large radius | `22px` | Main photo frame |
| Pill radius | `999px` | Controls and option chips |

Typography uses the system font stack with a restrained Georgia serif for selected editorial headings. This avoids network font requests and keeps the page portable/offline-capable.

## Layout system

1. **Top bar:** application identity and a clear live/paused state.
2. **Stage:** flexible viewport region; the photo is centered and constrained by both width and height.
3. **Control area:** slide position, Previous/Next, Pause/Play, Settings and Stop actions. A video-style progress timeline is intentionally omitted.
4. **Responsive rules:** compact controls on phones; reduced vertical chrome on short landscape displays; larger margins on very wide displays.

The image element is positioned over the exact stage box and uses `width:100%`, `height:100%`, and `object-fit:contain`. It fills the available stage proportionally without cropping or stretching. This deliberately leaves neutral letterboxing around images whose aspect ratio differs from the screen.

## Photo transitions

| Name | Effect | Intended feel |
|---|---|---|
| Classic fade | Brief opacity-only entrance | Calm change between still photographs |
| Instant cut | No entrance movement | Traditional presentation slide change |

Zoom, pan, blur, reveal and continuous camera-like movement are deliberately excluded. The default is Classic fade. There is no animated video-style progress timeline.

## Playback behaviour

- Automatic rotation uses an HTML meta refresh and a configurable interval (5, 8, 10, 15 or 20 seconds).
- The first request starts with the oldest eligible photograph. Each later request selects only the next higher eligible photo ID. The sequence does not loop.
- The last photograph remains visible for its selected interval. The next navigation opens a completion overlay over that final photo.
- The public completion overlay offers Home and Replay. The owner-only completion overlay offers Dashboard, Home and Replay.
- Previous/Next navigation does not wrap at either end.
- Pause freezes the current photograph and suppresses automatic navigation. Resume proceeds to the next photo, or to the completion panel if the current slide is the final one.
- Transition and duration choices are ordinary same-origin links. Changing a setting pauses the slideshow until the visitor resumes.
- If no eligible photos exist, the display shows a designed empty state and checks again after 30 seconds.
- If the database is unavailable, a neutral service state is shown and retried after 30 seconds.
- Press F11 to use the browser's full-screen mode. Browsers do not permit a web page to force full-screen mode without a user gesture.

## Accessibility and performance decisions

- All controls are real links with descriptive accessible names and visible keyboard focus.
- Auto-advancing content has a visible Pause/Resume control.
- Reduced-motion preferences are respected.
- The only animated photo transition is a short opacity fade; Instant cut has no animation. No zoom, pan, blur or animated timeline is used.
- The original uploaded image is served through the existing endpoint; CSS does not upscale it beyond the display bounds or crop it. If source photos are very large, a future server-side thumbnail/derivative pipeline could improve bandwidth, but that would require an explicit image-processing decision and tests.
- The public image endpoint rechecks eligibility when the image is requested. A paused page also revalidates the selected photo on each page request; consent withdrawal and moderation are therefore still enforced on subsequent requests.

## Known limitations

- With the project's no-JavaScript rule, each photo change is a full page request. Classic fade is an opacity entrance, not a simultaneous two-image crossfade. A true overlapping crossfade, persistent keyboard shortcuts, and an in-page fullscreen button would require JavaScript or a different media presentation architecture.
- Live photo rotation, consent withdrawal while a browser remains on a static paused page, database query behaviour, and real display-device rendering must be verified against the user's XAMPP database and browser.
- The browser's F11 key is the supported full-screen route; the page cannot guarantee kiosk/fullscreen operation by itself.


## Display fit correction (2026-11-25)

The slideshow frame is constrained to the remaining viewport area between the top bar and controls. The image uses intrinsic `width:auto` and `height:auto`, plus `max-width:100%`, `max-height:100%`, `max-inline-size:100%`, and `max-block-size:100%` with centered `object-fit:contain`. This keeps the complete photograph visible without forced stretching, cropping or needless upscaling. Small/low-resolution source photographs may therefore appear smaller; a larger screen cannot recreate detail that is not present in the original file. The initial duration is 5 seconds; choices are 5, 8, 10, 15 and 20 seconds.


## Final-slide completion

Both public and owner-only sequences are finite. The page determines whether another validated image exists before it chooses the timed refresh destination. On the final slide, the refresh target is a completion route that revalidates and retains the last image while showing a modal-style completion panel. The public version offers Home and Replay; the personal version offers Dashboard, Home and Replay. Previous navigation is disabled on the first image. No database migration is required.
