#!/usr/bin/env python3
"""
Build the static page content for WebsitesGH Shop.

Voice: plain, direct, numerate. The positioning is "the shop that checks the
numbers", so these pages never oversell. They state what happens, in order,
with real figures where we have them.

Contact details live in one CONTACT dict below. Change them here and rerun,
or edit the pages in WordPress afterwards. Nothing is hardcoded twice.

Run: python3 content-factory/engine/build_pages.py
Writes: inc/setup-data/pages.json
"""

import json
import os

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, "..", ".."))
OUT = os.path.join(ROOT, "inc", "setup-data")

CONTACT = {
    "phone_local": "054 214 8020",
    "phone_intl": "233542148020",
    "momo_number": "054 214 8020",
    "momo_name": "Confirm the registered name on the MoMo prompt before sending",
    "city": "Accra, Ghana",
    "hours": "Monday to Saturday, 8am to 7pm",
    "cutoff": "4pm",
}


def wa(text="Hello, I would like to order."):
    from urllib.parse import quote
    return "https://wa.me/" + CONTACT["phone_intl"] + "?text=" + quote(text)


PAGES = []


def page(slug, title, content, template=""):
    p = {"slug": slug, "title": title, "content": content.strip()}
    if template:
        p["template"] = template
    PAGES.append(p)


# --------------------------------------------------------------------------
page("about", "About", """
<h2>We check the numbers</h2>
<p>Most shops selling appliances in Ghana copy the specification off the box and
put it online. Nobody checks whether it is true. We do, and we publish the working
so you can check it yourself.</p>

<h3>Two examples</h3>
<p><strong>Blenders.</strong> Almost every blender sold in Ghana is advertised as
4500W or 8000W. Ghanaian mains is 230V and a standard Type G socket is rated 13A,
so the most any socket in your house can deliver is 2,990W. An 8000W load would
need 34.8 amps. The plug fuse blows long before that. The number on the box is a
peak marketing figure, not what the motor draws.</p>
<p><strong>Power banks.</strong> A power bank advertised at 30,000mAh does not put
30,000mAh into your phone. The cells run at 3.7V and USB output is 5V, and the
conversion loses energy as heat. Real delivered capacity is roughly 15,500 to
18,900mAh. That is about three to four full charges on a typical phone, not six.</p>

<h3>Why tell you this</h3>
<p>Because you already know your power bank underdelivers. You have owned one.
Being the shop that explains why, with the arithmetic on the page, is worth more
to us than one extra sale made on a number we knew was false.</p>

<h3>How we sell</h3>
<p>Pay on delivery. The rider brings the item, you look at it, then you pay. We
hold stock with dealers in Accra and dispatch the same day on orders confirmed
before """ + CONTACT["cutoff"] + """, so you are not waiting weeks for a shipment
to clear.</p>

<h3>Where we are</h3>
<p>""" + CONTACT["city"] + """. We deliver across Accra and Tema same day, and
nationwide in two to four working days. Reach us on """ + CONTACT["phone_local"] + """
or on <a href=\"""" + wa() + """\" target=\"_blank\" rel=\"noopener\">WhatsApp</a>.</p>
""")

# --------------------------------------------------------------------------
page("how-to-order", "How to Order", """
<p><strong>Quick answer.</strong> Choose your item, give us your name, phone number
and location, and we call you to confirm. The rider brings it, you check it, then
you pay. No payment is taken before delivery.</p>

<h2>The four steps</h2>
<ol>
<li><strong>Choose your item.</strong> Add it to your cart on the site, or send us
the product name on WhatsApp if that is easier.</li>
<li><strong>Give us three things.</strong> Your name, a phone number that reaches
you, and where you are. That is all checkout asks for.</li>
<li><strong>We call you to confirm.</strong> Usually within the hour during
working hours. We confirm the item, the price and the delivery window before
anything moves.</li>
<li><strong>Check it, then pay.</strong> The rider hands it to you. Open it, look
at it, test it if you want. Then pay the rider, or pay by mobile money on the
spot.</li>
</ol>

<h2>Questions people ask</h2>

<h3>Do I pay before delivery?</h3>
<p>No. Pay on delivery is our default and most orders go that way. If you would
rather pay ahead by mobile money or bank transfer, you can, but you are never
required to.</p>

<h3>What if the item is not what I expected?</h3>
<p>Do not pay for it. You inspect before money changes hands, which is the whole
point of paying on delivery. Tell the rider and send it back with them.</p>

<h3>How fast is delivery?</h3>
<p>Same day in Accra and Tema for orders confirmed before """ + CONTACT["cutoff"] + """.
Next morning after that. Two to four working days for the rest of the country.</p>

<h3>Can I order without using the website?</h3>
<p>Yes. Message us on <a href=\"""" + wa() + """\" target=\"_blank\" rel=\"noopener\">WhatsApp</a>
or call """ + CONTACT["phone_local"] + """ and we will take the order there.</p>

<h3>Do I need an account?</h3>
<p>No. Checkout does not require one.</p>
""")

# --------------------------------------------------------------------------
page("delivery-and-payment", "Delivery and Payment", """
<p><strong>Quick answer.</strong> Same day delivery in Accra and Tema on orders
confirmed before """ + CONTACT["cutoff"] + """. Two to four working days nationwide.
Pay the rider on delivery, or by mobile money to """ + CONTACT["momo_number"] + """.</p>

<h2>Delivery</h2>
<table>
<thead><tr><th>Where</th><th>When</th></tr></thead>
<tbody>
<tr><td>Accra and Tema</td><td>Same day if confirmed before """ + CONTACT["cutoff"] + """, otherwise next morning</td></tr>
<tr><td>Kumasi, Takoradi, Cape Coast</td><td>1 to 2 working days</td></tr>
<tr><td>Rest of Ghana</td><td>2 to 4 working days</td></tr>
</tbody>
</table>
<p>Delivery cost depends on your location and is confirmed on the call before
dispatch. We tell you the figure before the rider leaves, never after he arrives.</p>

<h2>Payment</h2>

<h3>Pay on delivery, our default</h3>
<p>The rider brings the item. You inspect it. You pay him. Cash or mobile money,
both work at the door.</p>

<h3>Mobile money</h3>
<p>If you prefer to pay ahead, send to <strong>""" + CONTACT["momo_number"] + """</strong>.
""" + CONTACT["momo_name"] + """. Send us the transaction ID on WhatsApp and we
dispatch once it clears.</p>

<h3>Bank transfer</h3>
<p>Available for larger orders. Ask us on the confirmation call and we will send
the details.</p>

<h2>What we do not do</h2>
<p>We do not ask for payment before you have spoken to a person from our side.
If anyone contacts you claiming to be us and asks you to send money to a number
other than """ + CONTACT["momo_number"] + """, it is not us. Call """ + CONTACT["phone_local"] + """
and check.</p>
""")

# --------------------------------------------------------------------------
page("returns", "Returns", """
<p><strong>Quick answer.</strong> Inspect the item at the door before you pay. If
it is wrong or damaged, refuse it and pay nothing. After that, you have 7 days for
a faulty item.</p>

<h2>At the door</h2>
<p>This is the easiest moment to solve a problem and it costs you nothing. Open
the box in front of the rider. If the item is damaged, the wrong item, or not what
was described, hand it back and do not pay. There is no form and no argument.</p>

<h2>After delivery</h2>
<table>
<thead><tr><th>Situation</th><th>What happens</th><th>Window</th></tr></thead>
<tbody>
<tr><td>Faulty on arrival, or fails within the window</td><td>Replacement, or a full refund if we cannot replace it</td><td>7 days</td></tr>
<tr><td>Wrong item sent</td><td>We collect it and deliver the right one, at our cost</td><td>7 days</td></tr>
<tr><td>Changed your mind, item unused and complete</td><td>Refund minus the delivery cost</td><td>48 hours</td></tr>
<tr><td>Damaged through use, or missing parts</td><td>Not covered</td><td></td></tr>
</tbody>
</table>

<h2>How to start one</h2>
<p>Message <a href=\"""" + wa("Hello, I need help with an order.") + """\" target=\"_blank\" rel=\"noopener\">WhatsApp</a>
or call """ + CONTACT["phone_local"] + """ with your order number and a photo or
short video of the problem. The video helps and usually settles it in one message.</p>

<h2>Warranty</h2>
<p>Where a product carries a manufacturer warranty we tell you on the product page
and pass the paperwork to you. Where it does not, we say so rather than implying
one exists.</p>
""")

# --------------------------------------------------------------------------
page("contact", "Contact", """
<p>We answer fastest on WhatsApp.</p>
<ul>
<li><strong>WhatsApp:</strong> <a href=\"""" + wa() + """\" target=\"_blank\" rel=\"noopener\">""" + CONTACT["phone_local"] + """</a></li>
<li><strong>Phone:</strong> """ + CONTACT["phone_local"] + """</li>
<li><strong>Hours:</strong> """ + CONTACT["hours"] + """</li>
<li><strong>Location:</strong> """ + CONTACT["city"] + """</li>
</ul>

<h2>Ordering</h2>
<p>You do not need to email to place an order. Send the product name on WhatsApp
with your location and we will take it from there. See <a href="/how-to-order/">How
to Order</a>.</p>

<h2>Suppliers and dealers</h2>
<p>If you supply appliances or electronics in Accra and can dispatch same day, get
in touch. We are always adding dealers.</p>
""")

# --------------------------------------------------------------------------
page("privacy-policy", "Privacy Policy", """
<p>We collect the least we can get away with, because we do not want to be
responsible for data we do not need.</p>

<h2>What we collect</h2>
<p>Your name, phone number and delivery location when you place an order. That is
what checkout asks for and that is what we keep. We do not ask for your date of
birth, your ID, or your email unless you choose to give it.</p>

<h2>What we do with it</h2>
<p>We use it to call you, confirm the order and deliver it. We share your name,
phone number and location with the rider carrying your parcel, because he cannot
deliver it otherwise. We do not sell your details to anyone.</p>

<h2>Payment information</h2>
<p>We do not store card details. Pay on delivery means money changes hands at your
door. If you pay by mobile money, that transaction is handled by your mobile money
provider, not by us.</p>

<h2>Analytics</h2>
<p>We use Google Analytics to understand which pages people read and which products
they look at. It sets cookies in your browser. You can block them in your browser
settings and the site still works.</p>

<h2>Your data</h2>
<p>Ask us to delete your details and we will, unless we are required to keep an
order record. Contact us on """ + CONTACT["phone_local"] + """.</p>
""")

# --------------------------------------------------------------------------
page("terms", "Terms", """
<h2>Prices</h2>
<p>All prices are in Ghana cedis and include VAT where it applies. Delivery cost is
separate and is confirmed on the call before dispatch. Prices change when our dealer
cost changes, and the price that applies is the one confirmed on your call.</p>

<h2>Orders</h2>
<p>An order placed on the site is a request, not a contract. It becomes an order
when we call you and confirm the item, the price and the delivery window. If we
cannot supply an item we tell you on that call rather than sending a substitute.</p>

<h2>Stock</h2>
<p>Stock figures on the site are our best current information. If an item sells out
between your order and our call, we tell you and offer an alternative or cancel it
at no cost to you.</p>

<h2>Product information</h2>
<p>We publish specifications we have checked and we say so where a manufacturer
claim is not accurate. Where we quote a figure we show the source or the working.
If you find an error on this site, tell us and we will correct it.</p>

<h2>Delivery</h2>
<p>Delivery times are targets, not guarantees. Traffic, weather and location affect
them. We keep you informed rather than leaving you waiting.</p>

<h2>Returns</h2>
<p>See <a href="/returns/">Returns</a>.</p>
""")


def main():
    os.makedirs(OUT, exist_ok=True)
    path = os.path.join(OUT, "pages.json")
    with open(path, "w", encoding="utf-8") as f:
        json.dump(PAGES, f, indent=2, ensure_ascii=False)
    print("pages written:", len(PAGES))
    for p in PAGES:
        print("  /%-22s %-24s %5d chars"
              % (p["slug"] + "/", p["title"], len(p["content"])))
    blob = json.dumps(PAGES)
    print("\nem dash present:", "\u2014" in blob)
    print("momo number present:", CONTACT["momo_number"] in blob)


if __name__ == "__main__":
    main()
