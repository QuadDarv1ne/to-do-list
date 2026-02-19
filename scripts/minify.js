const fs = require('fs');
const path = require('path');
const Terser = require('terser');
const CleanCSS = require('clean-css');

const publicDir = path.join(__dirname, '..', 'public');
const jsDir = path.join(publicDir, 'js');
const cssDir = path.join(publicDir, 'css');

const cleanCSS = new CleanCSS({
    level: 2,
    compatibility: '*'
});

async function minifyJS() {
    const files = fs.readdirSync(jsDir).filter(f => f.endsWith('.js') && !f.endsWith('.min.js'));
    
    for (const file of files) {
        try {
            const inputPath = path.join(jsDir, file);
            const outputPath = path.join(jsDir, file.replace('.js', '.min.js'));
            
            const code = fs.readFileSync(inputPath, 'utf8');
            const result = await Terser.minify(code, {
                compress: true,
                mangle: true,
                format: {
                    comments: false
                }
            });
            
            if (result.code) {
                fs.writeFileSync(outputPath, result.code);
                console.log(`✓ Minified: ${file}`);
            }
        } catch (error) {
            console.error(`✗ Error minifying ${file}:`, error.message);
        }
    }
}

function minifyCSS() {
    const files = fs.readdirSync(cssDir).filter(f => f.endsWith('.css') && !f.endsWith('.min.css'));
    
    for (const file of files) {
        try {
            const inputPath = path.join(cssDir, file);
            const outputPath = path.join(cssDir, file.replace('.css', '.min.css'));
            
            const code = fs.readFileSync(inputPath, 'utf8');
            const result = cleanCSS.minify(code);
            
            if (result.styles) {
                fs.writeFileSync(outputPath, result.styles);
                console.log(`✓ Minified: ${file}`);
            }
        } catch (error) {
            console.error(`✗ Error minifying ${file}:`, error.message);
        }
    }
}

async function main() {
    console.log('Starting minification...\n');
    
    console.log('Minifying JavaScript:');
    await minifyJS();
    
    console.log('\nMinifying CSS:');
    minifyCSS();
    
    console.log('\n✓ Minification complete!');
}

main().catch(console.error);
