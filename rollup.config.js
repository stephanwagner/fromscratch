import resolve from '@rollup/plugin-node-resolve';
import babel from '@rollup/plugin-babel';
import terser from '@rollup/plugin-terser';

// Theme name
const theme = 'fromscratch';

// Production mode
const mode = process.env.MODE || 'prod';
const prod = mode === 'prod';

// Base path
const base = `themes/${theme}`;

export default [
  /**
   * Frontend JS
   */
  {
    input: `${base}/src/js/main/main.js`,
    output: {
      file: `${base}/assets/js/main${prod ? '.min' : ''}.js`,
      format: 'iife',
      sourcemap: !prod,
    },
    plugins: [
      resolve(),
      babel({
        babelHelpers: 'bundled',
        extensions: ['.js', '.jsx'],
        presets: [
          '@babel/preset-env',
          ['@babel/preset-react', { runtime: 'automatic' }]
        ]
      }),
      prod && terser()
    ]
  },

  /**
   * Admin JS
   */
  {
    input: `${base}/src/js/admin/admin.js`,
    output: {
      file: `${base}/assets/js/admin${prod ? '.min' : ''}.js`,
      format: 'iife',
      sourcemap: !prod,
    },
    plugins: [
      resolve(),
      babel({
        babelHelpers: 'bundled',
        extensions: ['.js', '.jsx'],
        presets: [
          '@babel/preset-env',
          ['@babel/preset-react', { runtime: 'automatic' }]
        ]
      }),
      prod && terser()
    ]
  },

  /**
   * Editor JS (optional)
   */
  {
    input: `${base}/src/js/editor/editor.js`,
    external: ['wp'],
    output: {
      file: `${base}/assets/js/editor${prod ? '.min' : ''}.js`,
      format: 'iife',
      globals: { wp: 'wp' },
      sourcemap: !prod,
    },
    plugins: [
      resolve(),
      babel({
        babelHelpers: 'bundled',
        extensions: ['.js', '.jsx'],
        presets: [
          '@babel/preset-env',
          ['@babel/preset-react', { runtime: 'classic' }]
        ]
      }),
      prod && terser()
    ]
  }
];
