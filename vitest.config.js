import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig( {
	plugins: [ react() ],
	test: {
		environment: 'jsdom',
		globals: true,
		css: false,
		include: [ 'admin/tests/**/*.test.{js,jsx}' ],
		setupFiles: [ './admin/tests/setup.js' ],
	},
} );
