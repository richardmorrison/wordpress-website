
/**
 * Use this file for JavaScript code that you want to run in the front-end
 * on posts/pages that contain this block.
 */

document.addEventListener('DOMContentLoaded', function() {
	const blocks = document.querySelectorAll('.wp-block-telex-block-eyes-follow-cursor');
	
	blocks.forEach(function(block) {
		const eyes = block.querySelectorAll('.eye');
		const pupils = block.querySelectorAll('.pupil');
		
		// Track mouse movement
		function handleMouseMove(event) {
			const mouseX = event.clientX;
			const mouseY = event.clientY;
			
			pupils.forEach(function(pupil) {
				const eye = pupil.closest('.eye');
				const eyeRect = eye.getBoundingClientRect();
				const eyeCenterX = eyeRect.left + eyeRect.width / 2;
				const eyeCenterY = eyeRect.top + eyeRect.height / 2;
				
				// Calculate angle between eye center and mouse
				const deltaX = mouseX - eyeCenterX;
				const deltaY = mouseY - eyeCenterY;
				const angle = Math.atan2(deltaY, deltaX);
				
				// Maximum distance pupil can move (radius of eye minus radius of pupil)
				const maxDistance = 30;
				
				// Calculate distance but cap it at maxDistance
				const distance = Math.min(
					maxDistance,
					Math.sqrt(deltaX * deltaX + deltaY * deltaY) / 10
				);
				
				// Calculate pupil position
				const pupilX = Math.cos(angle) * distance;
				const pupilY = Math.sin(angle) * distance;
				
				// Apply transform
				pupil.style.transform = 'translate(' + pupilX + 'px, ' + pupilY + 'px)';
			});
		}
		
		// Random winking
		function randomWink() {
			// Random interval between 3-8 seconds
			const nextWink = Math.random() * 5000 + 3000;
			
			setTimeout(function() {
				// Randomly choose left eye (0) or right eye (1) or both (2)
				const whichEye = Math.floor(Math.random() * 3);
				
				if (whichEye === 0 || whichEye === 2) {
					eyes[0].classList.add('winking');
					setTimeout(function() {
						eyes[0].classList.remove('winking');
					}, 200);
				}
				
				if (whichEye === 1 || whichEye === 2) {
					eyes[1].classList.add('winking');
					setTimeout(function() {
						eyes[1].classList.remove('winking');
					}, 200);
				}
				
				// Schedule next wink
				randomWink();
			}, nextWink);
		}
		
		// Start tracking mouse
		document.addEventListener('mousemove', handleMouseMove);
		
		// Start random winking
		randomWink();
	});
});
