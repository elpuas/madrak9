/**
 * Generate Phosphor Icons JSON for Icon Block
 *
 * This script extracts regular weight SVGs from @phosphor-icons/core
 * and generates a JSON file for use with the Icon Block plugin.
 */

const fs = require('fs');
const path = require('path');

// Path to the Phosphor icons core package (relative to this script's location)
const phosphorCorePath = path.join(__dirname, '..', 'node_modules', '@phosphor-icons', 'core');
const assetsPath = path.join(phosphorCorePath, 'assets', 'regular');
const outputPath = path.join(__dirname, '..', 'loader', 'icon-block-ollie', 'ollie-icons.json');
const badgeIconsPath = path.join(__dirname, '..', 'src', 'controls', 'icon-block-ollie', 'svgs', 'badges');
const paymentIconsPath = path.join(__dirname, '..', 'src', 'controls', 'icon-block-ollie', 'svgs', 'payments');

/**
 * Icons to exclude from the final output
 * These are icons unlikely to be used for agency, ecommerce, and marketing sites
 */
const excludedIcons = new Set([
	// Games - specific gaming icons not useful for business sites
	'alien',
	'baseball',
	'baseball-cap',
	'baseball-helmet',
	'basketball',
	'bowling-ball',
	'club',
	'diamond',
	'dice-five',
	'dice-four',
	'dice-one',
	'dice-six',
	'dice-three',
	'dice-two',
	'disc',
	'football',
	'football-helmet',
	'game-controller',
	'golf',
	'hockey',
	'joystick',
	'medal',
	'medal-military',
	'pac-man',
	'pinwheel',
	'playing-card',
	'poker-chip',
	'puzzle-piece',
	'racquet',
	'soccer-ball',
	'spade',
	'sword',
	'swords',
	'tennis-ball',
	'volleyball',

	// Chess pieces - not relevant for business
	'chess',
	'chess-bishop',
	'chess-king',
	'chess-knight',
	'chess-pawn',
	'chess-queen',
	'chess-rook',

	// Very niche/specialized icons
	'acorn',
	'anchor',
	'anchor-simple',
	'atom',
	'avocado',
	'axe',
	'baby',
	'baby-carriage',
	'backpack',
	'balloon',
	'bandaids',
	'barbell',
	'barcode',
	'barn',
	'bathtub',
	'beer-bottle',
	'beer-stein',
	'biohazard',
	'bird',
	'blueprint',
	'bone',
	'boules',
	'bow-arrow',
	'bowls-steam',
	'boxing-glove',
	'brain',
	'brandy',
	'bridge',
	'broom',
	'bucket',
	'butterfly',
	'cactus',
	'campfire',
	'car-profile',
	'cardholder',
	'cards',
	'cards-three',
	'carrot',
	'castle-turret',
	'cat',
	'cauldron',
	'cigarette',
	'cigarette-slash',
	'coat-hanger',
	'coffee-bean',
	'coin',
	'coins',
	'confetti',
	'cookie',
	'cooking-pot',
	'coral',
	'couch',
	'cow',
	'cowboy-hat',
	'crab',
	'crane',
	'crane-tower',
	'cricket',
	'cross',
	'crown',
	'crown-cross',
	'crown-simple',
	'diamond',
	'diamonds-four',
	'dog',
	'domino',
	'donut',
	'dress',
	'dresser',
	'drop',
	'drop-half',
	'drop-half-bottom',
	'drop-simple',
	'drop-slash',
	'drops',
	'drum',
	'dumbbell',
	'ear',
	'ear-slash',
	'egg',
	'egg-crack',
	'escalator-down',
	'escalator-up',
	'exam',
	'eyedropper',
	'eyedropper-sample',
	'eyes',
	'farm',
	'fence',
	'fingerprint',
	'fingerprint-simple',
	'finn-the-human',
	'fire-extinguisher',
	'fire-truck',
	'fish',
	'fish-simple',
	'fishing-pole',
	'flag-banner',
	'flag-banner-fold',
	'flag-checkered',
	'flag-pennant',
	'flame',
	'flashlight',
	'flip-horizontal',
	'flip-vertical',
	'flower',
	'flower-lotus',
	'flower-tulip',
	'flying-saucer',
	'football-helmet',
	'footprints',
	'fork',
	'fork-knife',
	'four-k',
	'framer-logo',
	'game-controller',
	'garage',
	'gas-can',
	'gas-pump',
	'gavel',
	'gear-fine',
	'ghost',
	'gift',
	'golf',
	'gps-slash',
	'grains',
	'grains-slash',
	'guitar',
	'hamburger',
	'hammer',
	'hand-fist',
	'hand-grab',
	'hand-peace',
	'hand-soap',
	'hand-waving',
	'handbag',
	'handbag-simple',
	'handshake',
	'hard-drive',
	'hard-drives',
	'hard-hat',
	'hash-straight',
	'head-circuit',
	'headset',
	'heart-half',
	'hexagon',
	'high-definition',
	'high-heel',
	'hockey',
	'hoodie',
	'horse',
	'horseshoe',
	'hourglass',
	'hourglass-high',
	'hourglass-low',
	'hourglass-medium',
	'hourglass-simple',
	'hourglass-simple-high',
	'hourglass-simple-low',
	'hourglass-simple-medium',
	'hurricane',
	'ice-cream',
	'infinity',
	'intersect',
	'intersect-square',
	'intersect-three',
	'island',
	'jacket',
	'jeep',
	'kanban',
	'key',
	'keyhole',
	'knife',
	'ladder',
	'ladder-simple',
	'lamp',
	'lamp-pendant',
	'lantern',
	'lasso',
	'leaf',
	'lectern',
	'lighthouse',
	'lifebuoy',
	'lightbulb',
	'lightbulb-filament',
	'lego',
	'lego-smiley',
	'lighthouse',
	'lily',
	'log',
	'lunchbox',
	'lungs',
	'mace',
	'magnet',
	'magnet-straight',
	'martini',
	'mask-happy',
	'mask-sad',
	'medal',
	'metaverse',
	'metronome',
	'microscope',
	'military-id',
	'mitten',
	'money',
	'moped',
	'moped-front',
	'mosque',
	'moth',
	'motorcycle',
	'mountains',
	'mouse-simple',
	'mug',
	'mushroom',
	'music-notes',
	'music-notes-minus',
	'music-notes-plus',
	'music-notes-simple',
	'needle',
	'nuclear-plant',
	'nut',
	'octagon',
	'onigiri',
	'orange',
	'orange-slice',
	'oven',
	'owl',
	'paint-brush',
	'paint-brush-broad',
	'paint-brush-household',
	'paint-bucket',
	'paint-roller',
	'palette',
	'pants',
	'paper-plane',
	'paper-plane-right',
	'paper-plane-tilt',
	'paperclip',
	'paperclip-horizontal',
	'parachute',
	'paragraph',
	'parallelogram',
	'park',
	'paw-print',
	'peace',
	'peanut',
	'pear',
	'pepper',
	'person',
	'person-arms-spread',
	'person-simple',
	'person-simple-bike',
	'person-simple-circle',
	'person-simple-hike',
	'person-simple-run',
	'person-simple-ski',
	'person-simple-snowboard',
	'person-simple-swim',
	'person-simple-tai-chi',
	'person-simple-throw',
	'person-simple-walk',
	'perspective',
	'pill',
	'pinwheel',
	'pint-glass',
	'pizza',
	'placeholder',
	'plant',
	'plug',
	'plugs',
	'plugs-connected',
	'police-car',
	'polygon',
	'popcorn',
	'pot',
	'potted-plant',
	'prescription',
	'pretzel',
	'popsicle',
	'power-outlet',
	'puzzle-piece',
	'racquet',
	'radio-button',
	'radioactive',
	'rainbow',
	'rainbow-cloud',
	'receipt',
	'record',
	'record-vinyl',
	'rectangle',
	'rectangle-dashed',
	'recycle',
	'rocking-chair',
	'roller-coaster',
	'rowing',
	'rug',
	'ruler',
	'sailboat',
	'salt',
	'scales',
	'scan',
	'scooter',
	'screwdriver',
	'seal',
	'seal-check',
	'seal-percent',
	'seal-question',
	'seal-warning',
	'seat',
	'seatbelt',
	'shield',
	'shield-check',
	'shield-checkered',
	'shield-chevron',
	'shield-plus',
	'shield-slash',
	'shield-star',
	'shield-warning',
	'shirt-folded',
	'shooting-star',
	'shopping-bag',
	'shopping-bag-open',
	'shopping-cart',
	'shopping-cart-simple',
	'shower',
	'shrimp',
	'signature',
	'signpost',
	'skull',
	'skype-logo',
	'slack-logo',
	'sliders',
	'sliders-horizontal',
	'smart-car',
	'smoking',
	'snapchat-logo',
	'sneaker',
	'sneaker-move',
	'snowflake',
	'soccer-ball',
	'sock',
	'sofa',
	'solar-panel',
	'solar-roof',
	'soundcloud-logo',
	'spade',
	'sparkle',
	'speaker-hifi',
	'speaker-high',
	'speaker-low',
	'speaker-none',
	'speaker-simple-high',
	'speaker-simple-low',
	'speaker-simple-none',
	'speaker-simple-slash',
	'speaker-simple-x',
	'speaker-slash',
	'speaker-x',
	'speedometer',
	'sphere',
	'spiral',
	'split-horizontal',
	'split-vertical',
	'spotlight',
	'spotify-logo',
	'spray-bottle',
	'square-half',
	'square-half-bottom',
	'square-logo',
	'square-split-horizontal',
	'square-split-vertical',
	'stack-minus',
	'stack-plus',
	'stack-simple',
	'stairs',
	'stamp',
	'standard-definition',
	'star-and-crescent',
	'star-four',
	'star-half',
	'star-of-david',
	'steam-logo',
	'steering-wheel',
	'steps',
	'stethoscope',
	'sticker',
	'stool',
	'strategy',
	'stripe-logo',
	'student',
	'subway',
	'suitcase',
	'suitcase-rolling',
	'suitcase-simple',
	'sunglasses',
	'swatches',
	'swap',
	'swimmer',
	'sword',
	'synagogue',
	'syringe',
	't-shirt',
	'table',
	'target',
	'taxi',
	'tea-bag',
	'teabag',
	'telegram-logo',
	'telescope',
	'television',
	'television-simple',
	'tennis-ball',
	'tent',
	'terminal',
	'terminal-window',
	'test-tube',
	'thermometer',
	'thermometer-cold',
	'thermometer-hot',
	'thermometer-simple',
	'ticket',
	'tidal-logo',
	'tie',
	'tiktok-logo',
	'tipi',
	'tire',
	'toilet',
	'toilet-paper',
	'tomb',
	'tombstone',
	'tote',
	'tote-simple',
	'towel',
	'tractor',
	'trademark',
	'trademark-registered',
	'traffic-cone',
	'traffic-sign',
	'traffic-signal',
	'trailer',
	'train',
	'train-regional',
	'train-simple',
	'tram',
	'translate',
	'trash',
	'trash-simple',
	'tree',
	'tree-evergreen',
	'tree-palm',
	'tree-structure',
	'tree-view',
	'trend-down',
	'trend-up',
	'triangle',
	'triangle-dashed',
	'triforce',
	'trolley',
	'trolley-suitcase',
	'trophy',
	'truck',
	'truck-trailer',
	'tumblr-logo',
	'twitch-logo',
	'umbrella',
	'umbrella-simple',
	'union',
	'unite',
	'unite-square',
	'usb',
	'van',
	'vault',
	'vector-three',
	'vector-two',
	'vibrate',
	'video-camera',
	'video-camera-slash',
	'video-conference',
	'vignette',
	'vinyl-record',
	'violin',
	'virtual-reality',
	'virus',
	'visor',
	'voicemail',
	'volleyball',
	'wall',
	'wallet',
	'warehouse',
	'washing-machine',
	'watch',
	'wave-sawtooth',
	'wave-sine',
	'wave-square',
	'wave-triangle',
	'waveform',
	'waveform-slash',
	'waves',
	'webcam',
	'webcam-slash',
	'webhooks-logo',
	'wechat-logo',
	'wheelchair',
	'wheelchair-motion',
	'wifi-high',
	'wifi-low',
	'wifi-medium',
	'wifi-none',
	'wifi-slash',
	'wifi-x',
	'wind',
	'windmill',
	'windows-logo',
	'wine',
	'wrench',
	'x-logo',
	'x-square',
	'yarn',
	'yin-yang',
	'youtube-logo',
]);

// Category mapping from Phosphor's category strings to user-friendly names
// These match the actual string values in the Phosphor icons metadata (from e enum)
const categoryMapping = {
	'arrows': { name: 'arrows', title: 'Arrows' },
	'brands': { name: 'brands', title: 'Brands' },
	'commerce': { name: 'commerce', title: 'Commerce' },
	'communications': { name: 'communication', title: 'Communication' },
	'design': { name: 'design', title: 'Design' },
	'technology & development': { name: 'development', title: 'Development' },
	'editor': { name: 'editor', title: 'Editor' },
	'finances': { name: 'finance', title: 'Finance' },
	'games': { name: 'games', title: 'Games' },
	'health & wellness': { name: 'health', title: 'Health & Wellness' },
	'maps & travel': { name: 'map', title: 'Maps & Travel' },
	'media': { name: 'media', title: 'Media' },
	'nature': { name: 'nature', title: 'Nature' },
	'objects': { name: 'objects', title: 'Objects' },
	'office': { name: 'office', title: 'Office' },
	'people': { name: 'people', title: 'People' },
	'system': { name: 'system', title: 'System' },
	'weather': { name: 'weather', title: 'Weather' },
	// Also handle alternative category names from 'a' enum (just in case)
	'communication': { name: 'communication', title: 'Communication' },
	'math & finance': { name: 'finance', title: 'Finance' },
	'office & editing': { name: 'office', title: 'Office' },
	'security & warnings': { name: 'security', title: 'Security' },
	'system & devices': { name: 'system', title: 'System' },
	'weather & nature': { name: 'weather', title: 'Weather' },
	'education': { name: 'education', title: 'Education' },
	'time': { name: 'time', title: 'Time' },
	// Custom categories
	'ecommerce-badges': { name: 'ecommerce-badges', title: 'Ecommerce Badges' },
	'ecommerce-payments': { name: 'ecommerce-payments', title: 'Ecommerce Payments' },
};

/**
 * Custom SVG icons metadata
 * These are manually defined for custom icons in the svgs folder
 */
const customIconsMetadata = {
	'ecom-badge-1': {
		name: 'free-shipping-badge',
		title: 'Free Shipping Badge',
		keywords: ['shipping', 'delivery', 'free', 'truck', 'badge', 'guarantee', 'ecommerce'],
	},
	'ecom-badge-2': {
		name: 'easy-return-badge',
		title: 'Easy Return Badge',
		keywords: ['return', 'refund', 'package', 'box', 'badge', 'guarantee', 'ecommerce'],
	},
	'ecom-badge-3': {
		name: 'secure-checkout-badge',
		title: 'Secure Checkout Badge',
		keywords: ['secure', 'checkout', 'lock', 'security', 'payment', 'badge', 'guarantee', 'ecommerce'],
	},
	'ecom-badge-4': {
		name: 'customer-satisfaction-badge',
		title: 'Customer Satisfaction Badge',
		keywords: ['satisfaction', 'thumbs up', 'happy', 'customer', 'badge', 'guarantee', 'ecommerce'],
	},
	'ecom-badge-5': {
		name: 'money-back-badge',
		title: 'Money Back Badge',
		keywords: ['money', 'back', 'refund', 'checkmark', 'badge', 'guarantee', 'ecommerce'],
	},
	'ecom-badge-6': {
		name: '30-day-support-badge',
		title: 'Customer Support Badge',
		keywords: ['30 day', 'support', 'chat', 'help', 'badge', 'guarantee', 'ecommerce'],
	},
	'ecom-badge-8': {
		name: 'top-rated-badge',
		title: 'Top Rated Badge',
		keywords: ['top', 'rated', 'star', 'sparkle', 'best', 'badge', 'guarantee', 'ecommerce'],
	},
	'ecom-badge-9': {
		name: 'one-year-warranty-badge',
		title: 'One Year Warranty Badge',
		keywords: ['warranty', 'one year', '1 year', 'ribbon', 'badge', 'guarantee', 'ecommerce'],
	},
	'ecom-badge-10': {
		name: '30-day-guarantee-badge',
		title: '30 Day Guarantee Badge',
		keywords: ['30 day', 'guarantee', 'ribbon', 'badge', 'ecommerce'],
	},
	'ecom-badge-11': {
		name: 'fast-delivery-badge',
		title: 'Fast Delivery Badge',
		keywords: ['fast', 'delivery', 'truck', 'shipping', 'express', 'badge', 'guarantee', 'ecommerce'],
	},
	'ecom-badge-12': {
		name: 'premium-quality-badge',
		title: 'Premium Quality Badge',
		keywords: ['premium', 'quality', 'trophy', 'award', 'best', 'badge', 'guarantee', 'ecommerce'],
	},
	'ecom-badge-13': {
		name: 'on-sale-badge',
		title: 'On Sale Badge',
		keywords: ['special', 'offer', 'sale', 'discount', 'price', 'tag', 'badge', 'ecommerce'],
	},
	'ecom-badge-14': {
		name: 'special-offer-badge',
		title: 'Special Offer Badge',
		keywords: ['special', 'offer', 'sale', 'discount', 'price', 'tag', 'badge', 'ecommerce'],
	},
};

/**
 * Payment icons metadata
 * These are for payment method icons in the new-icons folder
 */
const paymentIconsMetadata = {
	'amazon-pay': {
		name: 'amazon-pay',
		title: 'Amazon Pay',
		keywords: ['amazon', 'pay', 'payment', 'checkout', 'ecommerce'],
	},
	'american express': {
		name: 'american-express',
		title: 'American Express',
		keywords: ['amex', 'american', 'express', 'card', 'credit', 'payment', 'checkout', 'ecommerce'],
	},
	'apple-pay': {
		name: 'apple-pay',
		title: 'Apple Pay',
		keywords: ['apple', 'pay', 'payment', 'checkout', 'wallet', 'mobile', 'ecommerce'],
	},
	'bitcoin': {
		name: 'bitcoin',
		title: 'Bitcoin',
		keywords: ['bitcoin', 'btc', 'crypto', 'cryptocurrency', 'payment', 'ecommerce'],
	},
	'discover': {
		name: 'discover',
		title: 'Discover',
		keywords: ['discover', 'card', 'credit', 'payment', 'checkout', 'ecommerce'],
	},
	'google-pay': {
		name: 'google-pay',
		title: 'Google Pay',
		keywords: ['google', 'pay', 'payment', 'checkout', 'wallet', 'mobile', 'ecommerce'],
	},
	'klarna': {
		name: 'klarna',
		title: 'Klarna',
		keywords: ['klarna', 'buy now pay later', 'bnpl', 'payment', 'checkout', 'ecommerce'],
	},
	'mastercard': {
		name: 'mastercard',
		title: 'Mastercard',
		keywords: ['mastercard', 'card', 'credit', 'debit', 'payment', 'checkout', 'ecommerce'],
	},
	'paypal': {
		name: 'paypal',
		title: 'PayPal',
		keywords: ['paypal', 'payment', 'checkout', 'wallet', 'ecommerce'],
	},
	'shop-pay': {
		name: 'shop-pay',
		title: 'Shop Pay',
		keywords: ['shop', 'shopify', 'pay', 'payment', 'checkout', 'ecommerce'],
	},
	'square': {
		name: 'square',
		title: 'Square',
		keywords: ['square', 'payment', 'checkout', 'pos', 'ecommerce'],
	},
	'stripe': {
		name: 'stripe',
		title: 'Stripe',
		keywords: ['stripe', 'payment', 'checkout', 'processing', 'ecommerce'],
	},
	'venmo': {
		name: 'venmo',
		title: 'Venmo',
		keywords: ['venmo', 'payment', 'wallet', 'mobile', 'ecommerce'],
	},
	'visa': {
		name: 'visa',
		title: 'Visa',
		keywords: ['visa', 'card', 'credit', 'debit', 'payment', 'checkout', 'ecommerce'],
	},
	'zelle': {
		name: 'zelle',
		title: 'Zelle',
		keywords: ['zelle', 'payment', 'bank', 'transfer', 'ecommerce'],
	},
};

/**
 * Load badge icons from the svgs/badges folder
 */
function loadBadgeIcons() {
	const badgeIcons = [];

	if (!fs.existsSync(badgeIconsPath)) {
		console.log('Badge icons directory not found, skipping badge icons');
		return badgeIcons;
	}

	const svgFiles = fs.readdirSync(badgeIconsPath).filter(file => file.endsWith('.svg'));
	console.log(`Found ${svgFiles.length} badge icon SVG files`);

	for (const file of svgFiles) {
		const baseName = file.replace('.svg', '');
		const metadata = customIconsMetadata[baseName];

		if (!metadata) {
			console.log(`Warning: No metadata defined for badge icon "${baseName}", skipping`);
			continue;
		}

		const svgPath = path.join(badgeIconsPath, file);
		const svgContent = fs.readFileSync(svgPath, 'utf8');
		const cleanedSvg = extractSvgContent(svgContent);

		badgeIcons.push({
			name: `ollie-${metadata.name}`,
			title: metadata.title,
			icon: cleanedSvg,
			categories: ['ecommerce-badges'],
			keywords: metadata.keywords,
		});
	}

	return badgeIcons;
}

/**
 * Load payment icons from the svgs/payments folder
 */
function loadPaymentIcons() {
	const paymentIcons = [];

	if (!fs.existsSync(paymentIconsPath)) {
		console.log('Payment icons directory not found, skipping payment icons');
		return paymentIcons;
	}

	const svgFiles = fs.readdirSync(paymentIconsPath).filter(file => file.endsWith('.svg'));
	console.log(`Found ${svgFiles.length} payment icon SVG files`);

	for (const file of svgFiles) {
		const baseName = file.replace('.svg', '');
		const metadata = paymentIconsMetadata[baseName];

		if (!metadata) {
			console.log(`Warning: No metadata defined for payment icon "${baseName}", skipping`);
			continue;
		}

		const svgPath = path.join(paymentIconsPath, file);
		const svgContent = fs.readFileSync(svgPath, 'utf8');
		const cleanedSvg = extractSvgContent(svgContent);

		paymentIcons.push({
			name: `ollie-${metadata.name}`,
			title: metadata.title,
			icon: cleanedSvg,
			categories: ['ecommerce-payments'],
			keywords: metadata.keywords,
		});
	}

	return paymentIcons;
}

/**
 * Convert kebab-case to Title Case
 */
function kebabToTitle(str) {
	return str
		.split('-')
		.map(word => word.charAt(0).toUpperCase() + word.slice(1))
		.join(' ');
}

/**
 * Extract path data from SVG content
 */
function extractSvgContent(svgContent) {
	// Remove XML declaration if present
	svgContent = svgContent.replace(/<\?xml[^>]*\?>/g, '');

	// Clean up the SVG - remove unnecessary whitespace
	svgContent = svgContent.trim();

	return svgContent;
}

/**
 * Parse the icons metadata from the ESM module
 */
function parseIconsMetadata() {
	const indexPath = path.join(phosphorCorePath, 'dist', 'index.mjs');

	if (!fs.existsSync(indexPath)) {
		console.log('index.mjs not found, will use filename-based categorization');
		return null;
	}

	const content = fs.readFileSync(indexPath, 'utf8');

	// The file contains an array of icon objects after "const n = ["
	// We need to extract this array and parse it

	// First, extract all enum mappings for categories from the 'e' variable
	// Format: e = /* @__PURE__ */ ((i) => (i.ARROWS = "arrows", i.BRAND = "brands", ...))
	// We need to find all i.KEY = "value" patterns in the enum definitions

	const enumMap = {};

	// Find all i.KEY = "value" patterns in the first part of the file (before the icons array)
	const enumSection = content.substring(0, content.indexOf('const n = ['));
	const enumRegex = /i\.(\w+)\s*=\s*"([^"]+)"/g;
	let match;
	while ((match = enumRegex.exec(enumSection)) !== null) {
		enumMap[match[1]] = match[2];
	}

	console.log('Found category enums:', Object.keys(enumMap).length);
	console.log('Enum keys:', Object.keys(enumMap).join(', '));

	// Now extract the icons array
	// Find where "const n = [" starts
	const arrayStart = content.indexOf('const n = [');
	if (arrayStart === -1) {
		console.log('Could not find icons array');
		return null;
	}

	// Find the matching closing bracket
	let bracketCount = 0;
	let arrayEnd = -1;
	let inArray = false;

	for (let i = arrayStart; i < content.length; i++) {
		if (content[i] === '[') {
			bracketCount++;
			inArray = true;
		} else if (content[i] === ']') {
			bracketCount--;
			if (inArray && bracketCount === 0) {
				arrayEnd = i + 1;
				break;
			}
		}
	}

	if (arrayEnd === -1) {
		console.log('Could not find end of icons array');
		return null;
	}

	// Extract the array portion
	let arrayStr = content.substring(arrayStart + 'const n = '.length, arrayEnd);

	// Replace enum references with their string values
	// e.g., e.ARROWS -> "arrows"
	Object.entries(enumMap).forEach(([key, value]) => {
		const regex = new RegExp(`e\\.${key}`, 'g');
		arrayStr = arrayStr.replace(regex, `"${value}"`);
	});

	// Also handle 'a' enum references (figma categories - we'll just remove these)
	arrayStr = arrayStr.replace(/a\.\w+/g, '""');

	try {
		// Parse the array as JSON (after fixing any remaining issues)
		// Remove trailing commas before ] or }
		arrayStr = arrayStr.replace(/,(\s*[}\]])/g, '$1');

		// Quote unquoted property names (convert JS object literals to JSON)
		// Match property names at the start of objects or after commas
		arrayStr = arrayStr.replace(/(\{|\,)\s*(\w+)\s*:/g, '$1"$2":');

		const icons = JSON.parse(arrayStr);
		console.log(`Parsed ${icons.length} icon metadata entries`);
		return icons;
	} catch (e) {
		console.log('Error parsing icons array:', e.message);
		// Try to save the problematic string for debugging
		fs.writeFileSync('/tmp/phosphor-debug.json', arrayStr);
		console.log('Saved debug output to /tmp/phosphor-debug.json');
		return null;
	}
}

/**
 * Main function to generate the icons JSON
 */
async function generateIconsJson() {
	console.log('Generating Phosphor Icons JSON...');
	console.log(`Reading SVGs from: ${assetsPath}`);

	// Check if assets directory exists
	if (!fs.existsSync(assetsPath)) {
		console.error(`Error: Assets directory not found at ${assetsPath}`);
		process.exit(1);
	}

	// Get all SVG files
	const svgFiles = fs.readdirSync(assetsPath).filter(file => file.endsWith('.svg'));
	console.log(`Found ${svgFiles.length} SVG files`);

	// Load icons metadata
	const iconsMetadata = parseIconsMetadata();

	// Create metadata lookup
	const metadataLookup = {};
	if (iconsMetadata && Array.isArray(iconsMetadata)) {
		iconsMetadata.forEach(icon => {
			metadataLookup[icon.name] = icon;
		});
	}

	const icons = [];
	const categoriesUsed = new Set();

	let excludedCount = 0;
	for (const file of svgFiles) {
		// Extract icon name from filename (remove -regular.svg suffix if present)
		let iconName = file.replace('.svg', '').replace(/-regular$/, '');

		// Skip excluded icons
		if (excludedIcons.has(iconName)) {
			excludedCount++;
			continue;
		}

		// Read SVG content
		const svgPath = path.join(assetsPath, file);
		const svgContent = fs.readFileSync(svgPath, 'utf8');
		const cleanedSvg = extractSvgContent(svgContent);

		// Get metadata if available
		const metadata = metadataLookup[iconName] || {};

		// Build categories array
		const iconCategories = [];
		if (metadata.categories && Array.isArray(metadata.categories)) {
			metadata.categories.forEach(cat => {
				const mapped = categoryMapping[cat];
				if (mapped) {
					iconCategories.push(mapped.name);
					categoriesUsed.add(cat);
				}
			});
		}

		// Build keywords array from tags (filter out "*new*" and similar)
		const keywords = (metadata.tags || []).filter(tag => !tag.startsWith('*'));

		icons.push({
			name: `phosphor-${iconName}`,
			title: kebabToTitle(iconName),
			icon: cleanedSvg,
			categories: iconCategories.length > 0 ? iconCategories : ['objects'],
			keywords: keywords,
		});
	}

	console.log(`Excluded ${excludedCount} icons based on exclusion list`);

	// Build categories list from actually used categories
	const categories = [];
	categoriesUsed.forEach(catKey => {
		const mapped = categoryMapping[catKey];
		if (mapped) {
			categories.push(mapped);
		}
	});

	// Load and add badge icons
	const badgeIcons = loadBadgeIcons();
	if (badgeIcons.length > 0) {
		icons.push(...badgeIcons);
		// Add eCommerce Badges category if we have badge icons
		categories.push(categoryMapping['ecommerce-badges']);
		console.log(`Added ${badgeIcons.length} eCommerce badge icons`);
	}

	// Load and add payment icons
	const paymentIcons = loadPaymentIcons();
	if (paymentIcons.length > 0) {
		icons.push(...paymentIcons);
		// Add eCommerce Payments category if we have payment icons
		categories.push(categoryMapping['ecommerce-payments']);
		console.log(`Added ${paymentIcons.length} eCommerce payment icons`);
	}

	// Sort icons alphabetically
	icons.sort((a, b) => a.title.localeCompare(b.title));

	// Sort categories alphabetically
	categories.sort((a, b) => a.title.localeCompare(b.title));

	const output = {
		type: 'phosphor',
		title: 'Phosphor Icons',
		icons,
		categories,
	};

	// Ensure output directory exists
	const outputDir = path.dirname(outputPath);
	if (!fs.existsSync(outputDir)) {
		fs.mkdirSync(outputDir, { recursive: true });
	}

	// Write JSON file
	fs.writeFileSync(outputPath, JSON.stringify(output, null, '\t'));

	// Log category distribution
	const catCounts = {};
	icons.forEach(icon => {
		icon.categories.forEach(cat => {
			catCounts[cat] = (catCounts[cat] || 0) + 1;
		});
	});
	console.log('Category distribution:', catCounts);

	console.log(`Generated ${icons.length} icons with ${categories.length} categories`);
	console.log(`Output written to: ${outputPath}`);

	writeCoreIconAssets(icons);
}

/**
 * Emit the same icon set for the WP 7.1 core icon registry: one .svg
 * file per icon (registered lazily via file_path) plus a PHP manifest
 * consumed by loader/core-icons/core-icons.php. Icons that can't
 * survive core's kses allowlist are skipped (they stay available
 * through the Icon Block integration).
 */
function writeCoreIconAssets(icons) {
	const { buildCoreManifest } = require('./core-icons-lib');
	const coreIconsDir = path.join(__dirname, '..', 'loader', 'core-icons');
	const svgDir = path.join(coreIconsDir, 'svg');

	const collectionLabels = {
		'ollie': 'Ollie',
		'ollie-payments': 'Payments',
		'ollie-badges': 'Badges',
	};

	const { entries, skipped } = buildCoreManifest(icons);

	// Start clean so removed icons don't linger between generations.
	fs.rmSync(svgDir, { recursive: true, force: true });

	const phpEscape = (value) => value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
	const usedCollections = new Set(entries.map((entry) => entry.collection));
	const lines = [
		'<?php',
		'/**',
		' * Generated by scripts/generate-icons.js — do not edit by hand.',
		' *',
		' * Icon manifest for the WP core icon registry (wp_register_icon).',
		' * Files live in svg/<collection>/<name>.svg relative to this file.',
		' */',
		'',
		'return array(',
		"\t'collections' => array(",
	];
	for (const [slug, label] of Object.entries(collectionLabels)) {
		if (usedCollections.has(slug)) {
			lines.push(`\t\t'${slug}' => '${phpEscape(label)}',`);
		}
	}
	lines.push("\t),", "\t'icons' => array(");

	for (const entry of entries) {
		const dir = path.join(svgDir, entry.collection);
		fs.mkdirSync(dir, { recursive: true });
		fs.writeFileSync(path.join(dir, entry.fileName), entry.svg + '\n');
		lines.push(
			`\t\tarray( '${entry.collection}', '${entry.name}', '${phpEscape(entry.label)}' ),`
		);
	}
	lines.push("\t),", ');', '');

	fs.writeFileSync(path.join(coreIconsDir, 'manifest.php'), lines.join('\n'));

	console.log(
		`Core icon registry: ${entries.length} icons across ${usedCollections.size} collections`
	);
	if (skipped.length) {
		console.log(
			`Core icon registry skipped ${skipped.length} icon(s) (kses-unsafe or invalid name): ${skipped.join(', ')}`
		);
	}
}

generateIconsJson().catch(console.error);
