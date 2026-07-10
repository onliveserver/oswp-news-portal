# Post Carousel Block

A powerful and customizable WordPress Gutenberg block for displaying posts in a beautiful carousel/slider format.

## Features

### Query Settings
- **Posts to Show**: Control the number of posts (1-20)
- **Filter by Categories**: Select specific categories
- **Filter by Tags**: Select specific tags
- **Order By**: Date, Title, Random, or Modified
- **Sort Order**: Ascending or Descending

### Slider Settings
- **Slides to Show**: Display 1-6 slides at once
- **Slides to Scroll**: Scroll 1-6 slides at a time
- **Autoplay**: Enable/disable automatic sliding
- **Autoplay Speed**: Set delay between slides (1000-10000ms)
- **Infinite Loop**: Enable continuous loop
- **Navigation Dots**: Show/hide pagination dots
- **Navigation Arrows**: Show/hide prev/next arrows
- **Responsive**: Automatically adjusts for mobile and tablet

### Content Display Options
- **Featured Image**: Show/hide post thumbnails
- **Post Title**: Show/hide post titles
- **Excerpt**: Show/hide post excerpts with word limit control (5-100 words)
- **Read More Button**: Customizable text
- **Meta Information**: Toggle date, author, and category display

### Styling Options
- **Card Padding**: 0-60px
- **Card Margin**: 0-40px
- **Card Shadow**: None, Small, Medium, Large, Extra Large
- **Border Radius**: 0-50px
- **Hover Effects**: Enable/disable hover animations

### Typography
- **Title Font Size**: 12-48px
- **Title Color**: Custom color picker
- **Excerpt Font Size**: 10-24px
- **Excerpt Color**: Custom color picker
- **Meta Font Size**: 8-20px
- **Meta Color**: Custom color picker
- **Background Color**: Custom color picker

## Installation

The block is automatically registered when the OSWP Posts plugin is activated.

## Usage

1. Add a new block in the Gutenberg editor
2. Search for "Post Carousel Slider"
3. Insert the block
4. Configure settings in the right sidebar:
   - **Query Settings**: Select posts to display
   - **Slider Settings**: Configure carousel behavior
   - **Content Display**: Choose what information to show
   - **Meta Information**: Toggle date/author/category
   - **Card Styling**: Customize appearance
   - **Typography**: Adjust fonts and colors

## Technical Details

### Dependencies
- Slick Carousel (loaded via CDN)
- WordPress Dashicons (for icons)
- jQuery (WordPress core)

### File Structure
```
blocks/post-carousel/
├── block.json          # Block configuration
├── index.js            # Editor JavaScript
├── style.css           # Frontend styles
├── editor.css          # Editor-only styles
├── carousel.js         # Slick initialization
├── package.json        # NPM dependencies
└── README.md           # This file
```

### PHP Class
- **File**: `includes/Blocks/Post_Carousel_Block.php`
- **Namespace**: `OSWP\Posts\Blocks`
- **Purpose**: Block registration and server-side rendering

## Customization

### Custom CSS
Add custom styles to your theme targeting:
- `.oswp-post-carousel-wrapper` - Main wrapper
- `.oswp-carousel-card` - Individual cards
- `.oswp-carousel-card__title` - Post titles
- `.oswp-carousel-card__excerpt` - Excerpts
- `.oswp-carousel-card__meta` - Meta information

### Hooks & Filters
(Add custom filters/hooks as needed for extensibility)

## Browser Support
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers

## Performance
- Lazy loads images
- Minified assets
- CDN delivery for Slick Carousel
- Conditional asset loading (only loads when block is present)

## License
GPL-2.0-or-later
