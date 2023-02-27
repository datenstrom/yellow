---
Title: Home
TitleContent: Hey! I'm Pablo!
Layout: homepage
Description: Website of Pablo Morales
---
<style>
.full-width {
	left: 50%;
	margin-left: -50vw;
	margin-right: -50vw;
	max-width: 100vw;
	position: relative;
	right: 50%;
	width: 100vw;
}

.hero-container {
background-color: #EAE7DC;
position: relative;
  display: flex;
  width: 100%;
  height: 750px;
z-index: -1;
  justify-content: space-evenly;
  align-items: center;
}
.hero-text-container {
  margin-top: 10%;
  width: 25%;
  z-index: 1;
}
.hero-text-heading {
  color: #f7fafc;
}
.hero-text-description {
  color: #b5d4db;
  width: 80%;
}


/* typing animation effect */
    .wrapper {
	height: 100vh;
	/*This part is important for centering*/
	display: flex;
	align-items: center;
	justify-content: center;
	
  }


  .typing-demo {
	width: 16ch;
	animation: typing 3s steps(23), blink 0.5s step-end infinite alternate;
	white-space: nowrap;
	overflow: hidden;
	border-right: 3px solid;
	font-family: var(--font);
	font-size: 2em;
color: #E85A4F;
  }
  
  @keyframes typing {
	from {
	  width: 0;
	}
  }
  
  @keyframes blink {
	50% {
	  border-color: transparent;
	}
  }
</style>

