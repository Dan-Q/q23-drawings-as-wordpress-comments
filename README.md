## Draw Me A Comment

What if instead of _writing_ a comment on a WordPress blog post... you could _draw_ one?

https://github.com/user-attachments/assets/62516c45-7c13-4d79-ab93-9b9b0e42da60

This codebase contains the requisite PHP and JS code to add this capability, on a post-by-post basis, to the "Q23" theme used on [DanQ.me](https://danq.me/).
The theme itself is closed-source (and without a doubt too tied-in to Dan's very-specific requirements to be of use to others anyway), but there's probably
sufficient code in this repository that an ambitious soul could adapt it for use on their own WordPress site. Or even to convert it into a general-purpose
WordPress plugin.

In the meantime: to see it at work, visit [DanQ.me/draw-me-a-comment](https://danq.me/draw-me-a-comment)

### Design decisions

- It is **deliberately difficult**: few colours, no eraser, and clumsy controls partially level the playing field between talented artists and the rest of us.
- It works **with touch or mouse controls** so you can use whatever device you have.
- The output is **animated**: re-drawn in the order that you drew it (but potentially accelerated).
- It's **progressively-enhanced** so that regular comments still work just fine (even alongside drawn comments).

Some inspiration may have been taken from _[Drawful](https://www.jackboxgames.com/games/the-jackbox-party-pack-1/drawful)_.

### How does it work?

For posts with a particular piece of metadata set, some Javascript is injected (users without Javascript continue to see the regular comment form). The
Javascript replaces the comment text box with a `<canvas>`, and adds code to allow drawing on it.

A `fetch` call retrieves a colour palette from the server: the palette is generated in PHP based on a combination of factors including the time, post ID, and
number of already-existing comments, and the resulting palette is digitally signed to prevent modification by the artist.

Each action (begin touch, move while touching, end touch, change pen colour) on the canvas is recorded by writing it to the (now-hidden) comment `<textarea>`.
Coordinates are rounded somewhat to save space. The commands are all a single letter to represent the operation, followed by one or two comma-separated numbers.
The whole thing is preceeded by an identification header and the signed palette obtained earlier. The database column to which comments are written has been
widened to accomodate them.

When outputting a comment, these instructions are converted into an SVG. The SVG is broken into multiple `<g>`roups each consisting of some number of strokes
(the number of strokes increases with each iteration, to gradually "accelerate" slow drawings). This can be drawn directly, as is is the case in the /wp-admin
dashboard, but on the front-end an `IntersectionObserver` detects when the comment comes into the scroll area and it's then _animated_, drawing the strokes in
the same order as the original artist did. Clicking a partially-redrawn comment skips the animation to the end.
