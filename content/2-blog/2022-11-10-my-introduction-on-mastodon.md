---
Title:  My #introduction on Mastodon
Published: 2022-11-10 11:26:05
Author: Pablo Morales
Layout: blog
Tag: mastodon, social media, flying away from twitter, chaos, November 2022
---
<style>
.container-frame {
  position: relative;
  overflow: hidden;
  width: 100%;
  padding-top: 56.25%; /* 16:9 Aspect Ratio (divide 9 by 16 = 0.5625) */
}

/* Then style the iframe to fit in the container div with full height and width */
.responsive-iframe {
  position: absolute;
  top: 0;
  left: 0;
  bottom: 0;
  right: 0;
  width: 100%;
  height: 100%;
}

.flex-container {
display: flex;
}

.flex-child {
    flex: 1;
}  

.flex-child:first-child {
    margin-right: 20px;
flex-wrap: wrap;
} 


.mastodon-img {
text-align: center;
}
.mastadon-img {
padding: 4em;
}

</style>
<div class="mastadon-img" markdown="1">
[image https://joinmastodon.org/logos/wordmark-black-text.svg]
</div>

## Pablo joined Mastodon! 
! <a href="#introduction-post">Click here to see my #introduction post at the bottom! Don't forget to follow me on Mastodon!</a>

We all know the news of the fire storm that is happening with Twitter and lighter fluid known as Elon Musk is throwing at it.

Mastodon is a social media service that is free. It pretty much acts like twitter with the same functionality but the name of the functions are named differently.

* post "toots" (instead of tweets), 
* follow other people and organizations, 
* favorite (like) and 
* boost (retweet) posts from other people.

I decided to join the community and so far I am enjoying it. When I mean joined, I actually created my own Mastodon instance on my server. More on this later. So for I am loving the decentralized platform and being able to control various aspects to it.  My first experience with federation was the use of OwnCloud and its variants such as NextCloud. This concept has been around for quite some time. 

I chose to host my own instance because I like my domain (lifeofpablo.com) and it gave me an opportunity to learn how to manage an instance and learn how to be a user as well. It's been a great experience. If there is question or something I don't know I visit the [Mastodon Documentation](https://docs.joinmastodon.org/) . This where the instructions are located to install your own instance. The key  to installing your own instance is making sure Node.js is setup correctly on your server. It's pretty straight forward.

##My Instance Setup:

<div class="flex-container">

  <div class="flex-child magenta" markdown="1">
### Back-End
* Domain Setup: [https://social.lifeofpablo.com](https://social.lifeofpablo.com)
* My username [pablo [at] pablolifeofpablo.com](https://social.lifeofpablo.com/@pablo)
    * Please follow me 😀
* Install using [Mastodon Documentation](https://docs.joinmastodon.org/) 

  </div>
  
  <div class="flex-child green" markdown="1">
  ###Front-End  
* Single User Mode (Just Me)
    * At this time no registrations (Please follow me!)
* I love that i can use my main domain as the communicative user domain.
  </div>
  
</div>



##Goals:
<div class="flex-container">

  <div class="flex-child magenta" markdown="1">
### Background
I would like to use Mastodon and the oAuth (used for login system) as a way to build apps, not necessarily clients. These apps would be an extension to my Mastodon instance. I'd use the login system to login to these apps to pull and use data.  

  </div>
  
  <div class="flex-child green" markdown="1">
  ###Steps 
* Use Mastodon as backend for authentication
* Build Node.js app using Mastodon (oAuth) as a login system for an internal app. 
    * [A tutorial I found for good foundation](https://charmed.blog/how-to-add-login-with-mastodon-using-nodejs/) 
* Create a dashboard where I can see metrics, trends, push system wide notifications, server maintenance, etc. Essentially a backend management.
* Move many of the administration features to the dashboard with the option of allowing features to be available.
  </div>
  
</div>

##Conclusion
It's been great getting to use Mastodon on the server-side and as a user. I see a great future for Mastodon and other decentralized, federated services (and other terminology) out there. 

I'm sure my goals/vision will grow on what I can do with my Mastodon instance. I know I will eventually migrate and upgrade server resources such as RAM, storage, processing power. I also need to make sure I concious about how much energy and being carbon neutral or carbon negative.

##Introduction Post
<div class="container-frame" markdown="1">
<iframe src="https://social.lifeofpablo.com/@pablo/109320684546936205/embed" id="introduction" class="responsive-iframe mastodon-embed" style="max-width: 100%; border: 0" width="100%" height="5wh" allowfullscreen="allowfullscreen"></iframe><script src="https://social.lifeofpablo.com/embed.js" async="async"></script>

</div>





