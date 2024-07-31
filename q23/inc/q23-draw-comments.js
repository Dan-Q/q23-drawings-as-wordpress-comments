(()=>{

// Constants
const canvasWidth = 980;
const canvasHeight = 500;
const brushWidth = 6;
const postId = document.querySelector('.post').dataset.postId;
const paletteDefaultBorder = 'calc( var(--step--2) / 4) solid #ccc';
const paletteSelectedBorder = 'calc( var(--step--2) / 2) solid #000';

fetch(`/_q23j/q23/v1/palette?post_id=${postId}`).then(r=>r.json()).then(paletteData=>{

  const palette = paletteData.palette;
  const commentHeader = `Q23DRW:${postId}:${paletteData.palette.join('')}:${paletteData.epoch}:${paletteData.sig}:\nP0 `;

  let penDown = false;
  let penWentDownAt = [];

  // Add canvas to comment form
  const comment = document.getElementById('comment');
  if( ! comment ) return;
  comment.value = commentHeader;
  comment.style.display = 'none';
  const canvasWrapper = document.createElement('div');
  canvasWrapper.style.position = 'relative';
  const canvas = document.createElement('canvas');
  canvas.width = canvasWidth;
  canvas.height = canvasHeight;
  canvas.style.width = '100%';
  canvas.style.aspectRatio = `${canvasWidth} / ${canvasHeight}`;
  canvas.style.border = '1px solid #ccc';
  canvas.style.borderRadius = '3px';
  canvas.style.touchAction = 'none';
  canvasWrapper.appendChild(canvas);
  comment.after(canvasWrapper);

  // Add palette
  let i = 0;
  for(const color of palette) {
    const button = document.createElement('button');
    button.className = 'q23drw-color-button';
    button.style.backgroundColor = color;
    button.style.width = 'var(--step-5)';
    button.style.aspectRatio = '1';
    button.style.border = (0 === i ? paletteSelectedBorder : paletteDefaultBorder);
    button.style.borderRadius = '50%';
    button.style.position = 'absolute';
    button.style.right = 'calc( -0.5 * var(--step-5) )';
    button.style.top = `calc( 5px + ( ( var(--step-5) + 5px ) * ${i} )`;
    button.dataset.id = i;
    button.addEventListener('click', e=>{
      e.preventDefault();
      document.querySelectorAll('.q23drw-color-button').forEach(b=>b.style.border = paletteDefaultBorder);
      button.style.border = paletteSelectedBorder;
      context.strokeStyle = color;
      comment.value += `P${button.dataset.id} `;
    });
    canvasWrapper.appendChild(button);
    i++;
  }

  // Get context
  const context = canvas.getContext('2d');
  context.strokeStyle = palette[0];
  context.lineWidth = brushWidth;

  // Add clear button too
  const submit = document.getElementById('submit');
  submit.value = '🖌️ Send';
  const clear = document.createElement('button');
  clear.innerText = '❌ Clear';
  clear.style.padding = '3px var(--step-1)';
  clear.style.border = '1px solid #ccc';
  clear.style.lineHeight = '1.65';
  clear.style.marginLeft = 'var(--step--1)';
  submit.after(clear);
  clear.addEventListener('click', e=>{
    e.preventDefault();
    context.clearRect(0, 0, canvasWidth, canvasHeight);
    comment.value = commentHeader;
    // reset palette selection:
    context.strokeStyle = palette[0];
    const paletteButtons = document.querySelectorAll('.q23drw-color-button');
    paletteButtons.forEach(b=>b.style.border = paletteDefaultBorder);
    paletteButtons[0].style.border = paletteSelectedBorder;
  });

  // Rounding to 3 decimal places is good enough and shorter
  function roundMouseCoordinate(coordinate) {
    return Math.round(coordinate * 10) / 10;
  }

  // Get the (rounded) mouse/touch coordinates from an event
  function getPointerCoordinates(event) {
    const boundings = canvas.getBoundingClientRect();
    const mouseX = (event.clientX - boundings.left) * canvas.width / boundings.width;
    const mouseY = (event.clientY - boundings.top) * canvas.height / boundings.height;
    return [ roundMouseCoordinate( mouseX ), roundMouseCoordinate( mouseY ) ];
  }

  // When clicking/touching, engage pen and start drawing
  canvas.addEventListener('pointerdown', function(event) {
    if(!event.isPrimary) return; // ignore non-left button/secondary touches
    penDown = true;
    const mouse = getPointerCoordinates(event);
    penWentDownAt = mouse; // save this so we can later detect "clicks" as well as "click-and-drags"

    // Start Drawing
    context.beginPath();
    context.moveTo(mouse[0], mouse[1]);
    comment.value += `M${mouse[0]},${mouse[1]} `;
  });

  // When moving, draw a line (if pen engaged)
  canvas.addEventListener('pointermove', function(event) {
    if(!penDown) return;
    const mouse = getPointerCoordinates(event);

    context.lineTo(mouse[0], mouse[1]);
    context.stroke();
    comment.value += `L${mouse[0]},${mouse[1]} `;
  });

  // When unclicking, disengage pen
  canvas.addEventListener('pointerup', function(event) {
    if(event.button !== 0) return; // ignore non-left button
    penDown = false;

    const mouse = getPointerCoordinates(event);
    if(penWentDownAt[0] == mouse[0] && penWentDownAt[1] == mouse[1]) {
      // this was an in-place "click", not a "click-and-drag": drop a "dot"
      context.arc(mouse[0], mouse[1], brushWidth/2, 0, 2 * Math.PI);
      context.fillStyle = context.strokeStyle;
      context.fill();
      comment.value += `C${mouse[0]},${mouse[1]} `;
      return;
    }
    context.lineTo(mouse[0], mouse[1]);
    context.stroke();
    comment.value += `L${mouse[0]},${mouse[1]} `;
  });

});

})();
