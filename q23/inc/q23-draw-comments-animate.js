// Animated drawing
(()=>{

const observer = new IntersectionObserver((entries, observer)=>{
  entries.forEach((entry)=>{
  	if(entry.isIntersecting){
			entry.target.classList.add('visible');
			observer.unobserve(entry.target);
		}
  });
}, { threshold: 0.25 });
for(svg of document.querySelectorAll('.q23-slow-svg')) {
	observer.observe(svg);

	svg.addEventListener('click', ()=>{
		for(e of Array.from(svg.querySelectorAll('path, circle'))) {
			e.style.transition = 'none'; // on clicking an SVG, skip through all the transitions
		}
	});
}

})();
