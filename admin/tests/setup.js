import '@testing-library/jest-dom/vitest';

// jsdom lacks a few browser APIs that @wordpress/components touches.
if ( ! window.matchMedia ) {
	window.matchMedia = () => ( {
		matches: false,
		media: '',
		onchange: null,
		addEventListener: () => {},
		removeEventListener: () => {},
		addListener: () => {},
		removeListener: () => {},
		dispatchEvent: () => false,
	} );
}

if ( ! global.ResizeObserver ) {
	global.ResizeObserver = class {
		observe() {}
		unobserve() {}
		disconnect() {}
	};
}
