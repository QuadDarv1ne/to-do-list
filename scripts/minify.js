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

// Recursively get all files in directory
function getAllFiles(dir, ext, fileList = []) {
    const files = fs.readdirSync(dir);
    
    files.forEach(file => {
        const filePath = path.join(dir, file);
        const stat = fs.statSync(filePath);
        
        if (stat.isDirectory()) {
            getAllFiles(filePath, ext, fileList);
        } else if (file.endsWith(ext) && !file.endsWith('.min' + ext)) {
            fileList.push(filePath);
        }
    });
    
    return fileList;
}

async function minifyJS() {
    const files = getAllFiles(jsDir, '.js');
    
    for (const inputPath of files) {
        try {
            const outputPath = inputPath.replace('.js', '.min.js');
            const code = fs.readFileSync(inputPath, 'utf8');
            
            const result = await Terser.minify(code, {
                compress: {
                    drop_console: true, // Remove console.* in production
                    drop_debugger: true,
                    pure_funcs: ['console.log', 'console.info', 'console.debug', 'console.warn']
                },
                mangle: true,
                format: {
                    comments: false
                }
            });
            
            if (result.code) {
                fs.writeFileSync(outputPath, result.code);
                console.log(`✓ Minified: ${path.relative(publicDir, inputPath)}`);
            }
        } catch (error) {
            console.error(`✗ Error minifying ${path.relative(publicDir, inputPath)}:`, error.message);
        }
    }
}

function minifyCSS() {
    const files = getAllFiles(cssDir, '.css');
    
    for (const inputPath of files) {
        try {
            const outputPath = inputPath.replace('.css', '.min.css');
            const code = fs.readFileSync(inputPath, 'utf8');
            const result = cleanCSS.minify(code);
            
            if (result.styles) {
                fs.writeFileSync(outputPath, result.styles);
                console.log(`✓ Minified: ${path.relative(publicDir, inputPath)}`);
            }
        } catch (error) {
            console.error(`✗ Error minifying ${path.relative(publicDir, inputPath)}:`, error.message);
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
