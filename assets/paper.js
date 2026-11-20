import { ShaderMount, paperTextureFragmentShader, getShaderNoiseTexture, getShaderColorFromString } from '@paper-design/shaders';

// Bake one flat paper tile (fibres + grain, no folds) with Paper Shaders, then expose it as --grain.
// One WebGL context for the whole app; sheets multiply this tile over their own --paper colour.
const SIZE = 640;

async function bake() {
    const noise = getShaderNoiseTexture();
    await noise.decode();

    const host = Object.assign(document.createElement('div'), { ariaHidden: 'true' });
    host.style.cssText = `position:fixed;left:-9999px;top:0;width:${SIZE}px;height:${SIZE}px`;
    document.body.append(host);

    const color = (c) => getShaderColorFromString(c);
    const mount = new ShaderMount(host, paperTextureFragmentShader, {
        u_fit: 0, u_scale: 1, u_rotation: 0, u_offsetX: 0, u_offsetY: 0, u_originX: .5, u_originY: .5,
        u_worldWidth: 0, u_worldHeight: 0, u_imageAspectRatio: 1,
        u_image: undefined, u_isImage: false, u_noiseTexture: noise,
        u_colorBack: color('#ffffff'), u_colorPaper: color('#ffffff'), u_colorShadow: color('#e4d9c0'),
        u_blending: 0, u_distortion: 0, u_clip: false, u_angle: 300, u_seed: 12,
        u_roughness: .07, u_roughnessSize: .15, u_roughnessRows: 0,
        u_fiber: .12, u_fiberSize: .15,
        u_folds: 0, u_foldSizeX: 1, u_foldSizeY: 1, u_foldOffsetX: 0, u_foldOffsetY: 0,
        u_wrinkles: 0, u_wrinkleSize: .65, u_crumples: 0, u_crumpleCount: 6, u_drops: 0,
    }, { preserveDrawingBuffer: true }, 0, 0, 1, SIZE * SIZE);

    // Give the first frame a moment to draw, then freeze it into an image.
    await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
    const canvas = host.querySelector('canvas');
    const blob = await new Promise((r) => canvas.toBlob(r, 'image/png'));
    mount.dispose();
    host.remove();
    if (blob) document.documentElement.style.setProperty('--grain', `url(${URL.createObjectURL(blob)})`);
}

bake().catch((e) => { console.error('paper.js', e); /* no WebGL2: sheets stay flat paper colour */ });
