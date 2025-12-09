## I know why you've come. 
You're really bored lol

<img width="935" height="627" alt="image" src="https://github.com/user-attachments/assets/dcfeae66-2726-4900-853c-f08af95cd358" />

# What is WeTube?
WeTube is a YouTube player by me, and everything I've ever gotten help from. It uses the same method as google classroom, edpuzzle, etc. to enable for an anti-ad, tracking free experience. This allows for the video to be unblocked at school.. and other work places ig.
Since this is a file, it cannot be blocked due to it not having a URL (I still wouldn't test that theory so keep it lowkey). Just download the file, open it, and watch some videos!

*(Update: Youtube no longer likes it when a video embed is put into a file://{url} format, soo just use the link below if possible.)*

Enjoy ;]

Hosted here: https://consistent-lavender-00kybqpd0f.edgeone.app/ <br> Also Hosted here: https://wetube.hyperphp.com/ <br> Also also also here: https://sites.google.com/hicksvilleschools.org/wetube/home <br> It's even a bookmarklet that replaces your current page with the code :] <br>
<!DOCTYPE html>
<html>
<body>

<h2>Bookmarklet Installer</h2>
<p>Drag the button below to your bookmarks bar:</p>

<a style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;" 
   href='javascript:(function(){ if (window.trustedTypes && window.trustedTypes.createPolicy) { window.trustedTypes.createPolicy("default", { createHTML: (string, sink) => string, createScript: (string, sink) => string, createScriptURL: (string, sink) => string }); } fetch("https://raw.githubusercontent.com/ItIsSpade/wetube/refs/heads/main/WeTube_v1.4.1.html") .then(response => response.text()) .then(html => { document.open(); document.write(html); document.close(); }) .catch(err => alert("Could not load WeTube: " + err)); })();'>
   🚀 WeTube Loader
</a>

</body>
</html>

If the link is blocked or something, either download the file and open it, and it will still work sometimes, or find a static html hosting site that isn't blocked and host it yourself!


# Extra info:
The reason this site can play videos like this is because it utilizes the YouTube api to get videos directly from YouTube, and then directly converts the video into YouTube's privay enhanced embed mode to make sure there are no tracking cookies until you actually click the watch button. Even then, there are very few. It's these cookies and the fact that they are linked to your school account/computer that is the main cause of the blocking of YouTube. This is just an automated way of putting the video id at the end of a youtube-nocookie link, with a nice UI and premade API keys to go along with it.


# "My WeTube isn't working :("

1. If your browser doesn't show thumbnails or the videos don't load, try quitting and reopening the browser.
2. I don't know of any other issues right now so as long as you use the website above, everything will be updated and most all video player issues will be fixed.
3. If you are ever lost in the site or something is being finicky, just reload the page and go from there.
